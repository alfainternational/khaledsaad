<?php

namespace Tests\Feature;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Models\AiUsageRecord;
use App\Models\AnswerFitness;
use App\Models\Project;
use App\Models\QuestionAssist;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Intake\Assist\AssistDraft;
use App\Modules\Intake\Assist\Contracts\AssistEngine;
use App\Modules\Intake\Assist\GatewayAssistEngine;
use App\Modules\Intake\Assist\QuestionDescriptor;
use App\Modules\Intake\Assist\QuestionLocator;
use App\Modules\Intake\Fitness\AnswerFitnessScorer;
use App\Modules\Measurement\QueryBudgetManager;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\AI\AIRequest;
use App\Support\AI\AIResponse;
use Closure;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * ذكاء المدخلات: ما نقدّمه قبل الإجابة، وما نقيسه فيها بعدها.
 *
 * الحدود المحروسة هنا أربعة، وكلها قواعد منتج لا تفاصيل تنفيذ:
 *   ١) «أجاب» لا يساوي «أجاب بما يكفي» — والفرق يظهر في الدرجة.
 *   ٢) المقترح فرضية موسومة، ولا يُخترع خيار خارج خيارات السؤال.
 *   ٣) لا استعلام واحد خارج سقف المساحة، والحجز قبل الاستدعاء لا بعده.
 *   ٤) عطل المساعدة لا يمنع الإجابة — هي معونة على السؤال لا شرط له.
 */
class InputIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    // ——— القياس الحتميّ ———

    #[Test]
    public function a_vague_audience_answer_scores_far_below_a_specific_one(): void
    {
        $scorer = app(AnswerFitnessScorer::class);

        $vague = $scorer->evaluate('target_audience', 'الجميع');
        $specific = $scorer->evaluate(
            'target_audience',
            'أصحاب مطاعم صغيرة في الرياض وجدة يعانون من ضعف الطلب في غير أوقات الذروة، '
            .'ومديرو تسويق في شركات أغذية يبحثون عن موزّع أسرع.',
        );

        $this->assertSame(AnswerFitness::VERDICT_INSUFFICIENT, $vague->verdict);
        $this->assertSame(AnswerFitness::VERDICT_SUFFICIENT, $specific->verdict);

        /*
         * هذا هو العطل الذي بُنيت هذه الطبقة لأجله: قبلها كانت الإجابتان تحصلان
         * على المعامل نفسه (1.0) لأن كلتيهما نصٌّ غير فارغ.
         */
        $this->assertGreaterThan($vague->score + 40, $specific->score);
    }

    #[Test]
    public function every_fitness_score_carries_its_own_arithmetic(): void
    {
        $verdict = app(AnswerFitnessScorer::class)->evaluate('target_audience', 'الناس');

        // §١٥: لا تعرض رقمًا لا تعرف كيف حُسب.
        $this->assertNotEmpty($verdict->basis);
        $this->assertNotEmpty($verdict->gaps);
        $this->assertSame('inferred', $verdict->toArray()['evidence_level']);
    }

    #[Test]
    public function choice_answers_are_not_measured_for_fitness(): void
    {
        $scorer = app(AnswerFitnessScorer::class);
        [, $project] = $this->owned();

        // كفاية الاختيار صفة السؤال لا الإجابة: قياسها يخلق رقمًا بلا معنى.
        $this->assertNull($scorer->score($project, 'audience_clarity', 'documented', 'select'));
        $this->assertFalse(AnswerFitnessScorer::measures('multiselect'));
        $this->assertTrue(AnswerFitnessScorer::measures('textarea'));
    }

    // ——— أثرها في التشخيص ———

    #[Test]
    public function a_vague_answer_lowers_the_axis_score_of_an_identical_fact(): void
    {
        $brain = app(BrainWriter::class);
        $scorer = app(AxisScorer::class);

        [, $weakProject] = $this->owned();
        [, $strongProject] = $this->owned();

        foreach ([$weakProject, $strongProject] as $project) {
            $brain->record($project, 'audience_clarity', 'documented', EvidenceLevel::Inferred, 'Intake');
            $brain->record($project, 'customer_pains', ['بطء التوصيل', 'غلاء الشحن', 'نقص المخزون'], EvidenceLevel::Inferred, 'Intake');
        }

        $brain->record($weakProject, 'target_audience', 'الجميع', EvidenceLevel::Inferred, 'Intake');
        app(AnswerFitnessScorer::class)->score($weakProject, 'target_audience', 'الجميع', 'textarea');

        $specific = 'أصحاب متاجر إلكترونية صغيرة في الرياض يحتاجون شحنًا أسرع، وعيادات أسنان في جدة تبحث عن مورّد ثابت.';
        $brain->record($strongProject, 'target_audience', $specific, EvidenceLevel::Inferred, 'Intake');
        app(AnswerFitnessScorer::class)->score($strongProject, 'target_audience', $specific, 'textarea');

        $weak = $scorer->score($weakProject, Axis::AudienceUnderstanding);
        $strong = $scorer->score($strongProject, Axis::AudienceUnderstanding);

        // نفس التغطية تمامًا — والدرجة مختلفة. هذا هو الفرق كله.
        $this->assertSame($strong->coverage, $weak->coverage);
        $this->assertLessThan($strong->score, $weak->score);

        // والفجوة تُسمّى بما ينقص الإجابة لا بغياب المدخل: المدخل موجود.
        $this->assertNotEmpty(array_filter(
            $weak->gaps,
            fn (string $gap) => str_contains($gap, 'الجمهور المستهدف —'),
        ));
    }

    #[Test]
    public function input_fitness_is_reported_beside_the_axis_score_not_folded_into_coverage(): void
    {
        $brain = app(BrainWriter::class);
        [, $project] = $this->owned();

        $brain->record($project, 'target_audience', 'الجميع', EvidenceLevel::Inferred, 'Intake');
        app(AnswerFitnessScorer::class)->score($project, 'target_audience', 'الجميع', 'textarea');

        $payload = app(AxisScorer::class)->score($project, Axis::AudienceUnderstanding)->toArray();

        // مقياس مستقل باسمه الرسمي، والتغطية تبقى تقول «وصلت المعلومة».
        $this->assertArrayHasKey(MetricKey::INPUT_FITNESS, $payload);
        $this->assertIsInt($payload[MetricKey::INPUT_FITNESS]);
        $this->assertGreaterThan(0.0, $payload[MetricKey::AXIS_COVERAGE]);
    }

    #[Test]
    public function an_axis_without_open_inputs_reports_no_fitness_rather_than_zero(): void
    {
        $brain = app(BrainWriter::class);
        [, $project] = $this->owned();

        $brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');

        // صفرٌ هنا يُقرأ «مدخلاته سيئة»، والمعنى «لا مدخل يُقاس» (§٤.٣).
        $this->assertNull(app(AxisScorer::class)->score($project, Axis::AiReadiness)->inputFitness);
    }

    // ——— المقترحات ———

    #[Test]
    public function a_generated_assist_is_reused_without_paying_twice(): void
    {
        [$user, $project] = $this->owned();
        [$run, $field] = $this->startedRun($user, $project);
        $calls = 0;

        $this->engineReturning(function () use (&$calls): AssistDraft {
            $calls++;

            return new AssistDraft(
                guide: 'إجابة كافية هنا تذكر من هم عملاؤك وأين وماذا يبحثون عنه في الرياض.',
                suggestions: [['label' => 'شريحة أولى', 'value' => 'أصحاب مطاعم في الرياض', 'why' => 'قطاعك ونطاقك.']],
                basis: ['وصف نشاطك', 'مدينتك'],
            );
        });

        $payload = ['surface' => 'tool', 'question_key' => $field, 'run_uuid' => $run->uuid];

        $this->actingAs($user)->postJson(route('app.assist.store', $project), $payload)->assertOk();
        $this->actingAs($user)->postJson(route('app.assist.store', $project), $payload)
            ->assertOk()
            ->assertJsonPath('data.guide', 'إجابة كافية هنا تذكر من هم عملاؤك وأين وماذا يبحثون عنه في الرياض.');

        $this->assertSame(1, $calls, 'أُعيد التوليد على سياق لم يتغيّر — أي دُفع ثمن المعلومة نفسها مرتين.');
        $this->assertSame(1, QuestionAssist::where('project_id', $project->id)->count());
    }

    #[Test]
    public function a_changed_business_context_invalidates_the_stored_assist(): void
    {
        [$user, $project] = $this->owned();
        [$run, $field] = $this->startedRun($user, $project);
        $calls = 0;

        $this->engineReturning(function () use (&$calls): AssistDraft {
            $calls++;

            return new AssistDraft(
                guide: 'دليل مبنيّ على ما نعرفه عن نشاطك حتى هذه اللحظة تحديدًا.',
                suggestions: [['label' => 'مقترح', 'value' => 'نصّ مقترح', 'why' => 'يناسب قطاعك.']],
            );
        });

        $payload = ['surface' => 'tool', 'question_key' => $field, 'run_uuid' => $run->uuid];
        $this->actingAs($user)->postJson(route('app.assist.store', $project), $payload)->assertOk();

        // حقيقة جديدة في الدماغ = نشاط صار موصوفًا بصورة أخرى.
        app(BrainWriter::class)->record($project, 'target_audience', 'أصحاب صيدليات في الدمام', EvidenceLevel::Inferred, 'Intake');

        $this->actingAs($user)->postJson(route('app.assist.store', $project), $payload)->assertOk();

        $this->assertSame(2, $calls, 'دليلٌ قديم بُني قبل أن نعرف جمهوره ما زال يُعرض عليه.');
    }

    #[Test]
    public function the_assist_never_invents_an_option_outside_the_question(): void
    {
        [$user, $project] = $this->owned();
        [$run] = $this->startedRun($user, $project);
        $descriptor = app(QuestionLocator::class)->inToolRun($run, $this->choiceFieldOf($run));

        $this->assertNotNull($descriptor);
        $this->assertTrue($descriptor->isChoice(), 'الحقل المختار ليس سؤال اختيار — الاختبار لا يفحص شيئًا.');

        $real = (string) $descriptor->options[0]['value'];

        /*
         * الفحص على المحرّك الحقيقي بمخرج نموذج مُختلق، لا على بديل مزيّف: قيمة
         * خارج الخيارات تعطي صفرًا صامتًا في خرائط نقاط المحاور لمن اختارها، أو
         * تسقط في التحقق برسالة «خيار غير معتمد» لا يفهمها أحد. النموذج يخالف
         * التعليمة أحيانًا، فالمصفاة على مخرجه لا على تعليمته.
         */
        $this->gatewayReturning([
            'guide' => 'اختر ما يصف حالتك فعلًا لا ما تتمنّاه، فالدرجة تُبنى على الوصف.',
            'suggestions' => [
                ['label' => 'خيار مُختلق', 'value' => 'قيمة-لا-توجد', 'why' => 'اختلقها النموذج من عنده.'],
                ['label' => 'خيار حقيقي', 'value' => $real, 'why' => 'موجود في خيارات السؤال فعلًا.'],
            ],
            'recommended_value' => 'قيمة-لا-توجد',
            'recommendation_reason' => 'سبب مبنيّ على خيار لا وجود له.',
        ]);

        $draft = app(GatewayAssistEngine::class)->compose($descriptor, ['business' => [], 'known_facts' => []]);

        $this->assertNull($draft->recommendedValue, 'رُشِّح خيار غير موجود في السؤال.');
        $this->assertNull($draft->recommendationReason, 'بقي سبب ترشيح لخيار أُسقط.');
        $this->assertSame([$real], array_column($draft->suggestions, 'value'));
    }

    #[Test]
    public function an_exhausted_cap_returns_no_assist_and_never_calls_the_provider(): void
    {
        [$user, $project] = $this->owned();
        [$run, $field] = $this->startedRun($user, $project);
        $project->workspace->forceFill(['monthly_query_limit' => 0])->save();

        $called = false;
        $this->engineReturning(function () use (&$called): AssistDraft {
            $called = true;

            return AssistDraft::none();
        });

        $this->actingAs($user)
            ->postJson(route('app.assist.store', $project), [
                'surface' => 'tool', 'question_key' => $field, 'run_uuid' => $run->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('data.guide', '')
            ->assertJsonPath('data.suggestions', []);

        $this->assertFalse($called, 'استُدعي المزوّد رغم نفاد السقف — الحجز يجب أن يسبق الاستدعاء.');
    }

    #[Test]
    public function a_provider_failure_returns_the_reserved_places_and_does_not_block_the_question(): void
    {
        [$user, $project] = $this->owned();
        [$run, $field] = $this->startedRun($user, $project);

        $this->engineReturning(fn () => throw new RuntimeException('المزوّد لا يستجيب.'));

        $this->actingAs($user)
            ->postJson(route('app.assist.store', $project), [
                'surface' => 'tool', 'question_key' => $field, 'run_uuid' => $run->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('data.suggestions', []);

        $budget = app(QueryBudgetManager::class)->budgetFor($project->workspace)->fresh();

        // لم يحصل على مقترح فلا يُحاسَب — كما في النسخ الصوتي.
        $this->assertSame(0, $budget->reserved);
        $this->assertSame(0, $budget->consumed);
    }

    #[Test]
    public function the_assist_is_charged_against_the_workspace_cap_with_its_real_cost(): void
    {
        [$user, $project] = $this->owned();
        [$run, $field] = $this->startedRun($user, $project);

        $this->engineReturning(function (): AssistDraft {
            // البوابة الحقيقية تكتب سجل التكلفة؛ نحاكي ذلك لأن الاختبار بلا شبكة.
            AiUsageRecord::create([
                'stage' => 'question_assist',
                'provider' => 'fake',
                'model' => 'fake-model',
                'input_tokens' => 900,
                'output_tokens' => 300,
                'cost_usd' => 0.0021,
                'status' => 'success',
            ]);

            return new AssistDraft(
                guide: 'دليل كافٍ لسؤال واحد، مبنيّ على ما وصفتَه عن نشاطك حتى الآن.',
                suggestions: [['label' => 'مقترح', 'value' => 'نصّ مقترح جاهز', 'why' => 'يناسب قطاعك ونطاقك.']],
            );
        });

        $this->actingAs($user)->postJson(route('app.assist.store', $project), [
            'surface' => 'tool', 'question_key' => $field, 'run_uuid' => $run->uuid,
        ])->assertOk();

        $budget = app(QueryBudgetManager::class)->budgetFor($project->workspace)->fresh();

        $this->assertSame(1, $budget->consumed);
        $this->assertSame(0, $budget->reserved);
        $this->assertEqualsWithDelta(0.0021, $budget->cost_usd, 0.000001);
    }

    #[Test]
    public function a_stranger_cannot_ask_for_assist_on_someone_elses_business(): void
    {
        [$user, $project] = $this->owned();
        [$run, $field] = $this->startedRun($user, $project);

        $this->actingAs(User::factory()->create())
            ->postJson(route('app.assist.store', $project), [
                'surface' => 'tool', 'question_key' => $field, 'run_uuid' => $run->uuid,
            ])
            ->assertNotFound();
    }

    #[Test]
    public function a_run_from_another_project_cannot_steer_the_assist_context(): void
    {
        [$user, $project] = $this->owned();
        [$otherUser, $otherProject] = $this->owned();
        [$foreignRun, $field] = $this->startedRun($otherUser, $otherProject);

        // تشغيلٌ صحيح ومفتاح سؤال صحيح، لكن لمشروع آخر: يُرفض ولا يُبنى دليل
        // على نشاط غريب.
        $this->actingAs($user)
            ->postJson(route('app.assist.store', $project), [
                'surface' => 'tool', 'question_key' => $field, 'run_uuid' => $foreignRun->uuid,
            ])
            ->assertNotFound();
    }

    // ——— القياس اللحظي ———

    #[Test]
    public function the_live_fitness_check_measures_without_storing_or_charging(): void
    {
        [$user, $project] = $this->owned();

        $this->actingAs($user)
            ->postJson(route('app.assist.fitness', $project), [
                'field_key' => 'target_audience',
                'type' => 'textarea',
                'value' => 'الجميع',
            ])
            ->assertOk()
            ->assertJsonPath('data.verdict', AnswerFitness::VERDICT_INSUFFICIENT)
            ->assertJsonPath('data.evidence_level', 'inferred');

        /*
         * القيمة مسوّدة في المتصفح لم تُرسَل بعد. حفظ درجة عنها كان سيُنشئ درجةً
         * لإجابة لا وجود لها في الدماغ، فتُخفض محورًا بمدخل لم يُحفظ قط.
         */
        $this->assertSame(0, AnswerFitness::where('project_id', $project->id)->count());

        $budget = app(QueryBudgetManager::class)->budgetFor($project->workspace)->fresh();
        $this->assertSame(0, $budget->consumed);
    }

    // ——— السطح ———

    #[Test]
    public function every_question_in_a_tool_form_offers_assistance_not_only_open_ones(): void
    {
        [$user, $project] = $this->owned();
        [$run] = $this->startedRun($user, $project);

        $html = $this->actingAs($user)
            ->get(route('app.runs.step', [$run->uuid, 1]))
            ->assertOk()
            ->getContent();

        $fields = $run->toolVersion->fields->where('step', 1);
        $choices = $fields->whereIn('type', ['select', 'radio', 'multiselect']);

        $this->assertGreaterThan(0, $choices->count(), 'الخطوة بلا سؤال اختيار — الاختبار لا يفحص شيئًا.');

        foreach ($fields as $field) {
            // «بلا استثناء» يعني أن الاختيار كذلك يحصل على ترشيح مسبَّب، لا المفتوح وحده.
            $this->assertStringContainsString(
                'data-question-key="'.$field->key.'"',
                $html,
                "السؤال {$field->key} بلا مساعدة.",
            );
        }

        $this->assertStringContainsString('فرضية', $html, 'المقترح بلا وسم يُقرأ حقيقة (§١٣).');
    }

    // ——— أدوات الاختبار ———

    /**
     * بوابة مزيّفة تعيد مخرجًا محدَّدًا — ليُفحص محرّك المساعدة الحقيقي بلا شبكة.
     *
     * @param  array<string, mixed>  $payload
     */
    private function gatewayReturning(array $payload): void
    {
        $this->app->bind(ArtificialIntelligenceGateway::class, fn () => new class($payload) implements ArtificialIntelligenceGateway
        {
            /** @param array<string, mixed> $payload */
            public function __construct(private readonly array $payload) {}

            public function run(AIRequest $request): AIResponse
            {
                return new AIResponse(
                    content: json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    provider: 'fake',
                    model: 'fake-model',
                    inputTokens: 100,
                    outputTokens: 50,
                    latencyMs: 5,
                    costUsd: 0.0001,
                    stage: $request->stage,
                );
            }

            public function stream(AIRequest $request, Closure $onChunk): AIResponse
            {
                return $this->run($request);
            }

            public function provider(): string
            {
                return 'fake';
            }

            public function modelForTier(string $tier): string
            {
                return 'fake-model';
            }
        });
    }

    private function engineReturning(Closure $factory): void
    {
        $this->app->bind(AssistEngine::class, fn () => new class($factory) implements AssistEngine
        {
            public function __construct(private readonly Closure $factory) {}

            public function compose(QuestionDescriptor $question, array $context): AssistDraft
            {
                return ($this->factory)($question, $context);
            }

            public function name(): string
            {
                return 'fake';
            }
        });
    }

    /**
     * @return array{0: ToolRun, 1: string}  التشغيل ومفتاح أول حقل مفتوح فيه
     */
    private function startedRun(User $user, Project $project): array
    {
        $this->seed(ToolCatalogSeeder::class);

        $run = app(ToolRunService::class)->start(
            $project,
            Tool::where('key', 'marketing-score')->firstOrFail(),
            $user,
        );

        $run->loadMissing('toolVersion.fields');
        $open = $run->toolVersion->fields
            ->whereIn('type', ['text', 'textarea'])
            ->sortBy('sort_order')
            ->first();

        return [$run, (string) ($open?->key ?? $run->toolVersion->fields->first()->key)];
    }

    private function choiceFieldOf(ToolRun $run): string
    {
        $run->loadMissing('toolVersion.fields');

        return (string) $run->toolVersion->fields
            ->whereIn('type', ['select', 'radio', 'multiselect'])
            ->first()
            ?->key;
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function owned(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);
        $project->brainFacts()->delete();
        $project->workspace->forceFill(['monthly_query_limit' => 50])->save();

        return [$user, $project->fresh()];
    }
}
