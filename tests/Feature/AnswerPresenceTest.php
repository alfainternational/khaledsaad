<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\AnswerPresenceCollector;
use App\Modules\AiReadiness\Contracts\AnswerEngine;
use App\Modules\AiReadiness\Models\PresenceProbe;
use App\Modules\AiReadiness\Models\PresenceRun;
use App\Modules\AiReadiness\QuestionBank;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\PresenceMetrics;
use App\Modules\Measurement\QueryBudgetManager;
use App\Modules\Measurement\SourceMap;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * استطلاع الحضور: أول قدرة بتكلفة متغيرة (المرحلة ٣).
 *
 * ما يُحرَس هنا ليس «هل تعمل» بل الحدود التي تجعل الرقم قابلًا للبيع: لا
 * قياس من عيّنة واحدة، ولا استعلام خارج الميزانية، والنص الخام محفوظ دائمًا.
 */
class AnswerPresenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_question_is_asked_at_least_three_times(): void
    {
        $project = $this->project();
        $this->engineReturning(['متجر البن الفاخر', 'قهوة الرياض']);

        // حتى لو طُلبت محاولة واحدة: «ظاهر / غائب» ممنوع، والصيغة «٢ من ٥» (§٤.٢).
        $run = app(AnswerPresenceCollector::class)->collect($project, questionCount: 2, attempts: 1);

        $this->assertSame(PresenceRun::MIN_ATTEMPTS, $run->attempts_per_question);
        $this->assertSame(6, $run->probes()->count());
    }

    #[Test]
    public function the_raw_response_is_kept_whole_for_every_attempt(): void
    {
        $project = $this->project();
        $text = "أفضل الخيارات:\n1. متجر البن الفاخر\n2. قهوة الرياض";
        $this->engineReturning(['متجر البن الفاخر', 'قهوة الرياض'], $text);

        $run = app(AnswerPresenceCollector::class)->collect($project, questionCount: 1);

        foreach ($run->probes as $probe) {
            // §١٤: التصنيف قد يتغيّر، والنص الخام هو الدليل الذي يسمح بإعادته
            // بلا إعادة دفع.
            $this->assertSame($text, $probe->raw_response);
        }
    }

    #[Test]
    public function no_query_runs_outside_the_budget(): void
    {
        $project = $this->project();
        $project->workspace->forceFill(['monthly_query_limit' => 5])->save();
        $this->engineReturning(['علامة أخرى']);

        // خمسة أسئلة × ثلاث محاولات = ١٥ على سقف ٥.
        $this->expectException(BudgetExhausted::class);

        try {
            app(AnswerPresenceCollector::class)->collect($project->fresh(), questionCount: 5);
        } finally {
            // الرفض قبل أي نداء: لا دورة أُنشئت ولا محاولة سُجِّلت.
            $this->assertSame(0, PresenceRun::count());
            $this->assertSame(0, PresenceProbe::count());
        }
    }

    #[Test]
    public function a_provider_failure_returns_the_unused_places(): void
    {
        $project = $this->project();
        $project->workspace->forceFill(['monthly_query_limit' => 100])->save();

        $this->app->bind(AnswerEngine::class, fn () => new class implements AnswerEngine
        {
            public function ask(string $question, string $locale = 'ar'): array
            {
                throw new RuntimeException('المزوّد لا يستجيب.');
            }

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-1';
            }
        });

        $run = app(AnswerPresenceCollector::class)->collect($project->fresh(), questionCount: 2);
        $budget = app(QueryBudgetManager::class)->budgetFor($project->workspace)->fresh();

        $this->assertSame(PresenceRun::STATUS_FAILED, $run->status);
        $this->assertSame(0, $budget->reserved, 'المواضع بقيت محجوزة بعد فشل المزوّد.');
        $this->assertEqualsWithDelta(0.0, $budget->cost_usd, 0.000001);
    }

    #[Test]
    public function the_metrics_follow_the_specification_exactly(): void
    {
        $project = $this->project();
        $run = $this->runWithProbes($project, [
            // سؤال أول: ذُكرت في محاولتين من ثلاث.
            ['q1', 1, true, ['نشاطي', 'منافس أ']],
            ['q1', 2, true, ['نشاطي', 'منافس ب']],
            ['q1', 3, false, ['منافس أ', 'منافس ب']],
            // سؤال ثانٍ: لم تُذكر إطلاقًا.
            ['q2', 1, false, ['منافس أ']],
            ['q2', 2, false, ['منافس أ']],
            ['q2', 3, false, ['منافس ب']],
        ]);

        $metrics = app(PresenceMetrics::class)->forRun($run, 'نشاطي');

        // mention_rate = 2 ÷ 6 = 0.3333
        $this->assertEqualsWithDelta(0.3333, $metrics[MetricKey::MENTION_RATE], 0.0001);

        // share_of_voice: مجموع ذكر كل العلامات في المحاولات الست = 9،
        // منها «نشاطي» مرتان → 2 ÷ 9 = 0.2222. المقام السوق كله لا محاولاتك،
        // ولذلك يختلف عن mention_rate رغم تشابه قراءتهما (§١٢).
        $this->assertEqualsWithDelta(0.2222, $metrics[MetricKey::SHARE_OF_VOICE], 0.0001);

        // consistency لكل سؤال: q2 صفر ويأتي أولًا، q1 = 2 ÷ 3.
        $this->assertSame('q2', $metrics['per_question'][0]['question_key']);
        $this->assertEqualsWithDelta(0.6667, $metrics['per_question'][1][MetricKey::CONSISTENCY], 0.0001);
    }

    #[Test]
    public function a_failed_attempt_does_not_count_as_an_absence(): void
    {
        $project = $this->project();
        $run = $this->runWithProbes($project, [
            ['q1', 1, true, ['نشاطي']],
            ['q1', 2, true, ['نشاطي']],
        ]);

        // محاولة ثالثة سقطت بعطل مزوّد: ليست «لم تُذكر فيها العلامة».
        PresenceProbe::create([
            'presence_run_id' => $run->id,
            'question_key' => 'q1',
            'question' => 'سؤال',
            'attempt' => 3,
            'status' => PresenceProbe::STATUS_FAILED,
        ]);

        $metrics = app(PresenceMetrics::class)->forRun($run->fresh(), 'نشاطي');

        // 2 ÷ 2 لا 2 ÷ 3: العطل لا يُقرأ حكمًا على النشاط (§١٢).
        $this->assertEqualsWithDelta(1.0, $metrics[MetricKey::MENTION_RATE], 0.0001);
        $this->assertSame(2, $metrics['basis']['successful_attempts']);
        $this->assertFalse($metrics['publishable'], 'دورة ناقصة عُرضت كأنها مكتملة.');
    }

    #[Test]
    public function citation_rate_is_measured_against_mentions_not_attempts(): void
    {
        $project = $this->project();
        $run = $this->runWithProbes($project, [
            ['q1', 1, true, ['نشاطي'], true],
            ['q1', 2, true, ['نشاطي'], false],
            ['q1', 3, false, ['منافس أ'], false],
        ]);

        $metrics = app(PresenceMetrics::class)->forRun($run, 'نشاطي');

        // 1 من 2 ذكر = 0.5، لا 1 من 3 محاولات.
        $this->assertEqualsWithDelta(0.5, $metrics[MetricKey::CITATION_RATE], 0.0001);
    }

    #[Test]
    public function the_source_map_weights_by_attempts_not_by_links(): void
    {
        $project = $this->project();
        $run = $this->runWithProbes($project, [
            ['q1', 1, true, ['نشاطي'], false, ['https://a.example/1', 'https://a.example/2']],
            ['q1', 2, true, ['نشاطي'], false, ['https://b.example/x']],
            ['q1', 3, true, ['نشاطي'], false, ['https://b.example/y']],
        ]);

        $map = app(SourceMap::class)->build([$run]);

        // a.example ذُكر برابطين في محاولة واحدة؛ b.example في محاولتين.
        // المرجع الفعلي للنموذج هو الثاني.
        $this->assertSame('b.example', $map['sources'][0]['host']);
        $this->assertSame(2, $map['sources'][0]['citations']);
        $this->assertSame(1, $map['sources'][1]['citations']);
    }

    #[Test]
    public function the_questions_are_written_as_a_real_buyer_types_them(): void
    {
        $project = $this->project();
        $questions = app(QuestionBank::class)->for($project);

        $this->assertGreaterThanOrEqual(QuestionBank::MIN_QUESTIONS, count($questions));

        foreach ($questions as $question) {
            // السؤال المترجم من الإنجليزية يُنتج جوابًا أكاديميًّا بلا أسماء،
            // فيخرج القياس بصفر ذكر لعلامة قد تكون ظاهرة تمامًا.
            $this->assertMatchesRegularExpression('/[؟]$/u', $question['text']);
            $this->assertStringContainsString('الرياض', $question['text']);
        }
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: bool, 3: array<int, string>, 4?: bool, 5?: array<int, string>}>  $rows
     */
    private function runWithProbes(Project $project, array $rows): PresenceRun
    {
        $run = PresenceRun::create([
            'project_id' => $project->id,
            'provider' => 'fake',
            'model' => 'fake-1',
            'questions_count' => count(array_unique(array_column($rows, 0))),
            'attempts_per_question' => 3,
            'status' => PresenceRun::STATUS_COMPLETED,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        foreach ($rows as $row) {
            PresenceProbe::create([
                'presence_run_id' => $run->id,
                'question_key' => $row[0],
                'question' => 'سؤال '.$row[0],
                'attempt' => $row[1],
                'brand_mentioned' => $row[2],
                'brands_mentioned' => $row[3],
                'site_cited' => $row[4] ?? false,
                'citations' => $row[5] ?? [],
                'raw_response' => 'نص',
                'status' => PresenceProbe::STATUS_OK,
            ]);
        }

        return $run->fresh();
    }

    /**
     * @param  array<int, string>  $brands
     */
    private function engineReturning(array $brands, ?string $text = null): void
    {
        $body = $text ?? collect($brands)
            ->map(fn ($brand, $index) => ($index + 1).'. '.$brand)
            ->implode("\n");

        $this->app->bind(AnswerEngine::class, fn () => new class($body) implements AnswerEngine
        {
            public function __construct(private readonly string $body) {}

            public function ask(string $question, string $locale = 'ar'): array
            {
                return ['text' => $this->body, 'latency_ms' => 120, 'cost_usd' => 0.001];
            }

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-1';
            }
        });
    }

    private function project(): Project
    {
        $user = User::factory()->create();

        $project = app(ProjectService::class)->create($user, [
            'name' => 'نشاطي',
            'industry' => 'محمصة قهوة',
            'geography' => 'الرياض، السعودية',
            'website' => 'https://example.test',
        ]);

        $project->workspace->forceFill(['monthly_query_limit' => 100])->save();

        return $project->fresh();
    }
}
