<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunPipeline;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolRunPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ToolCatalogSeeder::class);

        config()->set('ai.deepseek', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.deepseek.com',
            'model' => 'deepseek-v4-flash',
            'timeout' => 60,
            'tiers' => ['economy' => 'deepseek-v4-flash', 'standard' => 'deepseek-v4-flash', 'advanced' => 'deepseek-reasoner'],
        ]);
    }

    #[Test]
    public function it_produces_a_full_report_from_answers(): void
    {
        $this->fakeSuccessfulProvider();

        $run = $this->completedDraft();

        app(ToolRunPipeline::class)->handle($run);
        $run->refresh();

        $this->assertSame(ToolRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->base_score);
        $this->assertNotNull($run->snapshot, 'يجب تجميد لقطة المشروع قبل التحليل.');

        $report = $run->report;
        $this->assertInstanceOf(Report::class, $report);
        $this->assertSame('published', $report->status);
        $this->assertSame($run->base_score, $report->score);
        $this->assertCount(3, $report->findings);

        // كل مرحلة اكتملت
        $this->assertSame(
            count(ToolRunPipeline::STAGES),
            $run->stages()->where('status', 'completed')->count(),
        );

        // التكلفة مسجلة لكل استدعاء — متطلب FR-AI-02
        $this->assertGreaterThan(0, $run->usageRecords()->count());
        $this->assertNotNull($report->generated_by_model);
    }

    #[Test]
    public function a_finding_without_evidence_is_marked_as_an_assumption(): void
    {
        $this->fakeSuccessfulProvider();

        $run = $this->completedDraft();
        app(ToolRunPipeline::class)->handle($run);

        $report = $run->refresh()->report;

        // BR-007: النتيجة الثالثة في القالب بلا evidence، فيجب أن تُصنف افتراضًا
        // حتى لو ادعى النموذج أنها حقيقة.
        $withoutEvidence = $report->findings->firstWhere('title', 'نتيجة بلا دليل');

        $this->assertNotNull($withoutEvidence);
        $this->assertTrue($withoutEvidence->is_assumption);
        $this->assertSame(2, $report->evidenceBackedFindings()->count());

        // ضمان BR-007 الحتمي: أساس النتيجة الافتراضية يظهر في assumptions،
        // لأن النموذج لم يذكره في مصفوفة assumptions المُعادة.
        $this->assertTrue(
            collect($report->assumptions ?? [])->contains(
                fn (string $line) => str_contains($line, 'نتيجة بلا دليل'),
            ),
            'كل نتيجة افتراضية يجب أن يظهر أساسها في assumptions بعد التركيب.',
        );

        // احترام maxItems:10 — لا يتجاوز عدد الافتراضات حدّ المخطط.
        $this->assertLessThanOrEqual(10, count($report->assumptions ?? []));
    }

    #[Test]
    public function a_provider_failure_still_delivers_actionable_priorities_from_the_score(): void
    {
        // القاعدة الجديدة: فشل المزود لا يُنتج تقريرًا فارغًا. الأرضية الحتمية
        // تشتق أولويات وتوصيات حقيقية من درجة العميل، فلا يصل إلى باب مغلق.
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response(['error' => 'upstream down'], 503),
        ]);

        $run = $this->completedDraft();
        app(ToolRunPipeline::class)->handle($run);
        $run->refresh();

        $this->assertSame(ToolRun::STATUS_PARTIAL, $run->status, (string) $run->failure_reason);
        $this->assertNotNull($run->base_score);
        $this->assertSame(14, $run->answers()->count());

        $report = $run->report;
        $this->assertNotNull($report, 'التقرير الأساسي يجب أن يبقى متاحًا رغم فشل المزود.');
        $this->assertSame($run->base_score, $report->score);

        // لا تقرير بلا خطوة: نتائج وتوصيات مشتقة من الدرجة.
        $this->assertGreaterThan(0, $report->findings()->count());
        $this->assertGreaterThan(0, $report->recommendations()->count());
        $this->assertNotEmpty($report->next_step['title'] ?? null);

        // النتائج الحتمية ليست افتراضات: مبنية على إجابات العميل.
        $this->assertGreaterThan(0, $report->findings()->where('is_assumption', false)->count());
    }

    #[Test]
    public function an_invalid_json_output_is_retried_and_never_reaches_the_report(): void
    {
        // مخرج غير مطابق للمخطط، ثم مخرج سليم في المحاولة التالية.
        $attempt = 0;

        Http::fake(function () use (&$attempt) {
            $attempt++;

            $payload = $attempt <= 1
                ? ['findings' => 'ليست مصفوفة']
                : $this->stagePayload($attempt);

            return Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]);
        });

        $run = $this->completedDraft();
        app(ToolRunPipeline::class)->handle($run);
        $run->refresh();

        $this->assertContains($run->status, [ToolRun::STATUS_COMPLETED, ToolRun::STATUS_PARTIAL]);
        $this->assertGreaterThan(1, $run->usageRecords()->count());

        // لا يوجد نص خام غير صالح داخل أي قسم من التقرير.
        foreach ($run->report->sections as $section) {
            $this->assertIsArray($section->content_json);
        }
    }

    #[Test]
    public function the_prompt_version_locks_after_first_use(): void
    {
        $this->fakeSuccessfulProvider();

        $run = $this->completedDraft();
        app(ToolRunPipeline::class)->handle($run);

        $prompt = $run->toolVersion->prompts()->where('stage', 'synthesis')->first();
        $this->assertNotNull($prompt->locked_at, 'BR-012: البرومبت يُقفل بعد أول استخدام.');

        $this->expectExceptionMessage('لا يمكن تعديل برومبت مستخدم');
        $prompt->update(['content' => 'محتوى جديد']);
    }

    #[Test]
    public function a_specialist_tool_does_not_overwrite_the_projects_marketing_score(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع بدرجة مستقرة']);
        $project->forceFill(['latest_score' => 73])->save();

        $tool = Tool::where('key', 'brand-clarity')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user)
            ->load(['toolVersion.fields', 'toolVersion.tool', 'answers', 'project']);

        $baseline = new \ReflectionMethod(ToolRunPipeline::class, 'baseline');
        $baseline->invoke(app(ToolRunPipeline::class), $run);

        $this->assertSame(73, $project->fresh()->latest_score);
    }

    #[Test]
    public function a_full_diagnosis_run_completes_with_gaps_instead_of_failing_on_missing_data(): void
    {
        $this->fakeSuccessfulProvider();

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع ناقص']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        // خطوة واحدة فقط: بيانات ناقصة عمدًا. علَم التشخيص الشامل يسمح بالإكمال.
        app(ToolRunService::class)->saveStep($run, 1, [
            'business_model' => 'services',
            'description' => str_repeat('وصف واضح للخدمة ', 3),
            'geography' => 'الرياض',
            'monthly_budget' => 5000,
        ]);
        $run->forceFill(['allow_incomplete' => true])->save();

        app(ToolRunPipeline::class)->handle($run->refresh());
        $run->refresh();

        // لم يعد النقص يفشل التشغيل — يُكمل بفجوات معلنة (مكتمل أو جزئي).
        $this->assertContains($run->status, [ToolRun::STATUS_COMPLETED, ToolRun::STATUS_PARTIAL]);
        $this->assertNotSame(ToolRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->base_score, 'الدرجة الحتمية تُحسب حتى مع نقص المدخلات.');
    }

    private function completedDraft(): ToolRun
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع اختبار']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $answers = [
            1 => ['business_model' => 'services', 'description' => str_repeat('وصف واضح للخدمة المقدمة ', 3), 'geography' => 'الرياض', 'monthly_budget' => 5000],
            2 => ['primary_goal' => 'leads', 'value_proposition' => 'نسلّم خلال 48 ساعة أو المبلغ يُعاد', 'audience_clarity' => 'documented'],
            3 => ['active_channels' => ['seo', 'paid'], 'tracking_maturity' => 'basic', 'content_cadence' => 'weekly'],
            4 => ['landing_experience' => 'basic', 'retention_motion' => 'manual', 'sales_cycle' => 'medium', 'known_cac' => 120],
        ];

        foreach ($answers as $step => $input) {
            app(ToolRunService::class)->saveStep($run, $step, $input);
        }

        return $run->refresh();
    }

    private function fakeSuccessfulProvider(): void
    {
        $call = 0;

        Http::fake(function () use (&$call) {
            $call++;

            return Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => json_encode($this->stagePayload($call), JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 60],
            ]);
        });
    }

    /**
     * قالب مخرج يخدم كل المراحل: المُتحقق يقبل المفاتيح الزائدة ويرفض الناقصة،
     * فيمكن إرجاع كائن واحد يغطي كل المخططات.
     *
     * @return array<string, mixed>
     */
    private function stagePayload(int $call): array
    {
        return [
            'missing' => [],
            'conflicts' => [],
            'issues' => [],
            'headline' => "عنوان تحليلي واضح للقسم رقم {$call}",
            'points' => [
                ['text' => 'نقطة تحليلية مبنية على إجابات المستخدم مباشرة.', 'evidence' => 'القياس: زيارات فقط', 'is_assumption' => false],
                ['text' => 'نقطة ثانية ترجيحية عن سلوك الجمهور المتوقع.', 'is_assumption' => true],
            ],
            'summary' => 'ملخص تنفيذي يوضح الوضع الحالي وأهم ما يجب فعله في التسعين يومًا القادمة.',
            'confidence' => 72,
            'assumptions' => ['تقدير حجم السوق مبني على متوسط القطاع.'],
            'next_step' => [
                'title' => 'ركّب قياس التحويل',
                'description' => 'عرّف حدث تحويل واحدًا على الأقل واربطه بمصدر الزيارة خلال هذا الأسبوع.',
            ],
            'findings' => [
                [
                    'title' => 'القياس لا يصل إلى الإيراد',
                    'description' => 'التتبع الحالي يسجل الزيارات فقط، فلا يمكن نسب أي ريال إلى قناة.',
                    'category' => 'القياس',
                    'severity' => 'high',
                    'evidence' => 'إجابة حالة القياس: زيارات فقط',
                    'evidence_answer_ref' => 'tracking_maturity',
                    'confidence' => 90,
                    'is_assumption' => false,
                    'recommendations' => [
                        [
                            'title' => 'عرّف ثلاثة أحداث تحويل',
                            'description' => 'أضف أحداث نموذج وواتساب وشراء، واربطها بمصدر الزيارة خلال أسبوعين.',
                            'impact' => 'high',
                            'effort' => 'low',
                            'kpi_hint' => 'عدد التحويلات المنسوبة شهريًا',
                        ],
                    ],
                ],
                [
                    'title' => 'صفحة التحويل عامة',
                    'description' => 'الزيارات المدفوعة تصل إلى صفحة غير مخصصة للعرض المعلن عنه.',
                    'category' => 'التحويل',
                    'severity' => 'medium',
                    'evidence' => 'إجابة جاهزية الصفحات: صفحة عامة دون تحسين',
                    'evidence_answer_ref' => 'landing_experience',
                    'confidence' => 80,
                    'is_assumption' => false,
                    'recommendations' => [
                        [
                            'title' => 'ابنِ صفحة هبوط للعرض',
                            'description' => 'صفحة واحدة تعالج الاعتراض الأول وتحمل دعوة فعل واحدة فقط.',
                            'impact' => 'high',
                            'effort' => 'medium',
                        ],
                    ],
                ],
                [
                    'title' => 'نتيجة بلا دليل',
                    'description' => 'ترجيح عام عن سلوك الجمهور لا تسنده بيانات مدخلة من المستخدم.',
                    'category' => 'الجمهور',
                    'severity' => 'low',
                    'confidence' => 40,
                    'is_assumption' => false,
                    'recommendations' => [
                        [
                            'title' => 'وثّق شريحتين',
                            'description' => 'اكتب آلام واعتراضات شريحتين أساسيتين قبل أي حملة قادمة.',
                            'impact' => 'medium',
                            'effort' => 'low',
                        ],
                    ],
                ],
            ],
        ];
    }
}
