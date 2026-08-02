<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Tool;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ManualReportService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التقارير الصادرة قبل بناء الشرح تُشرح بأثر رجعي — بلا مساس بدرجتها.
 */
class BackfillScoreExplanationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, ToolCatalogSeeder::class]);
    }

    #[Test]
    public function it_explains_an_old_report_without_moving_its_score(): void
    {
        $report = $this->legacyReport();
        $scoreBefore = $report->score;

        $this->artisan('reports:backfill-score-explanation')->assertSuccessful();

        $content = $report->sections()->where('key', 'score')->firstOrFail()->content_json;

        $this->assertArrayHasKey('share', $content['breakdown'][0]);
        $this->assertArrayHasKey('question', $content['breakdown'][0]);
        $this->assertArrayHasKey('weights_basis', $content);

        // الدرجة المعروضة للعميل لا تتحرك: التقرير سجلّ لما قيل له وقتها.
        $this->assertSame($scoreBefore, $report->fresh()->score);
    }

    #[Test]
    public function it_refuses_to_touch_a_report_whose_score_would_move(): void
    {
        $report = $this->legacyReport();
        // محاكاة تغيّر القواعد بعد الإصدار: الدرجة المحفوظة لم تعد قابلة لإعادة الإنتاج.
        $report->forceFill(['score' => $report->score + 7])->save();

        $this->artisan('reports:backfill-score-explanation')->assertFailed();

        $content = $report->sections()->where('key', 'score')->firstOrFail()->content_json;

        // تُرك بلا شرح بدل أن يُعاد كتابته بدرجة مختلفة عمّا رآه العميل.
        $this->assertArrayNotHasKey('share', $content['breakdown'][0]);
        $this->assertSame($report->score, $report->fresh()->score);
    }

    /**
     * `--rescore` فعلٌ واعٍ يُطلب صراحةً: يصحّح الدرجة ويشرحها معًا.
     *
     * الحارس هنا مزدوج — يتحقق أن الخيار يعمل، وأن غيابه يبقي السلوك على
     * الترك. أي انزلاق يجعل أمر صيانة يغيّر أرقامًا رآها عملاء.
     */
    #[Test]
    public function rescore_corrects_the_drifted_score_only_when_asked(): void
    {
        $report = $this->legacyReport();
        $original = $report->score;
        $report->forceFill(['score' => $original + 7])->save();

        $this->artisan('reports:backfill-score-explanation', [
            '--rescore' => true,
            '--only' => (string) $report->id,
        ])->assertSuccessful();

        $report->refresh();

        $this->assertSame($original, $report->score, 'الدرجة تعود إلى ما تنتجه القواعد المنطبقة.');
        $this->assertArrayHasKey('share', $report->sections()->where('key', 'score')->firstOrFail()->content_json['breakdown'][0]);
    }

    #[Test]
    public function the_dry_run_writes_nothing(): void
    {
        $report = $this->legacyReport();

        $this->artisan('reports:backfill-score-explanation', ['--dry-run' => true])->assertSuccessful();

        $content = $report->sections()->where('key', 'score')->firstOrFail()->content_json;
        $this->assertArrayNotHasKey('share', $content['breakdown'][0]);
    }

    /**
     * تقرير حقيقي جُرِّد تفصيله من مفاتيح الشرح، كما هي تقارير ما قبل التغيير.
     */
    private function legacyReport(): Report
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر عسل']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $svc = app(ToolRunService::class);
        $run = $svc->start($project, $tool, $user);

        $svc->saveStep($run, 1, ['business_model' => 'services', 'description' => str_repeat('وصف واضح للخدمة ', 3), 'geography' => 'الرياض', 'monthly_budget' => 5000]);
        $svc->saveStep($run, 2, ['primary_goal' => 'leads', 'value_proposition' => 'نسلّم خلال 48 ساعة أو المبلغ يُعاد كاملًا', 'audience_clarity' => 'documented']);
        $svc->saveStep($run, 3, ['active_channels' => ['seo'], 'tracking_maturity' => 'basic', 'content_cadence' => 'weekly']);
        $svc->saveStep($run, 4, ['landing_experience' => 'basic', 'retention_motion' => 'manual', 'sales_cycle' => 'medium', 'known_cac' => 120]);

        $admin = User::factory()->create(['is_admin' => true]);
        $report = app(ManualReportService::class)->import($run->fresh(), $this->payload(), $admin);

        $section = $report->sections()->where('key', 'score')->firstOrFail();
        $content = $section->content_json;

        unset($content['weights_basis'], $content['total_weight'], $content['excluded']);

        foreach ($content['breakdown'] as $index => $row) {
            unset(
                $content['breakdown'][$index]['share'],
                $content['breakdown'][$index]['question'],
                $content['breakdown'][$index]['answer_label'],
                $content['breakdown'][$index]['why'],
                $content['breakdown'][$index]['scale'],
                $content['breakdown'][$index]['value'],
                $content['breakdown'][$index]['rule_type'],
            );
        }

        $section->forceFill(['content_json' => $content])->save();

        return $report->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $finding = fn (string $title) => [
            'title' => $title,
            'description' => 'شرح واضح لهذه النتيجة مبني على إجابات صاحب المشروع نفسه.',
            'severity' => 'high',
            'is_assumption' => false,
            'evidence' => 'من إجابته عن حالة القياس.',
            'recommendations' => [[
                'title' => 'خطوة تنفيذية واضحة',
                'description' => 'نفّذ هذه الخطوة خلال هذا الأسبوع بشكل محدد وقابل للقياس.',
                'impact' => 'high',
                'effort' => 'low',
            ]],
        ];

        return [
            'summary' => 'ملخص تنفيذي مكتوب بلغة صاحب المشروع يوضح أين هو الآن وما الذي يبدأ به فورًا.',
            'confidence' => 88,
            'assumptions' => [],
            'next_step' => [
                'title' => 'ابدأ بربط القياس',
                'description' => 'عرّف حدث الشراء واربطه بمصدر الزيارة قبل أي زيادة في الإنفاق.',
            ],
            'findings' => [
                $finding('لا تعرف من أين يأتي عملاؤك'),
                $finding('صفحة التحويل لا تقنع'),
                $finding('لا متابعة بعد أول شراء'),
            ],
        ];
    }
}
