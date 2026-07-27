<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ManualReportService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * المسار اليدوي: العميل يختار مراجعة بشرية، والآدمن ينزّل الإدخالات
 * ويرفع النتيجة، فتظهر بنفس بنية التقرير التلقائي موثّقة أنها يدوية.
 */
class ManualReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, ToolCatalogSeeder::class]);
    }

    #[Test]
    public function the_customer_can_ask_for_a_human_review_instead_of_the_pipeline(): void
    {
        Queue::fake();
        [$user, $run] = $this->completedRun();

        $this->actingAs($user)
            ->post(route('app.runs.manual', $run->uuid))
            ->assertRedirect(route('app.runs.status', $run->uuid));

        $run->refresh();
        $this->assertSame('manual', $run->delivery_mode);
        // لا يُدفع إلى خط الأنابيب التلقائي.
        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_mobile_api_can_request_the_same_human_review(): void
    {
        Queue::fake();
        [$user, $run] = $this->completedRun();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.runs.manual', $run->uuid))
            ->assertAccepted()
            ->assertJsonPath('data.status', ToolRun::STATUS_QUEUED);

        $this->assertSame('manual', $run->fresh()->delivery_mode);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_export_package_carries_questions_answers_and_the_required_schema(): void
    {
        [, $run] = $this->completedRun();

        $package = app(ManualReportService::class)->exportPackage($run);

        $this->assertArrayHasKey('instructions', $package);
        $this->assertArrayHasKey('required_output_schema', $package);
        $this->assertNotEmpty($package['questions_and_answers']);
        // السؤال بنصه مع إجابته: مكتفٍ بذاته للمعالجة الخارجية.
        $first = collect($package['questions_and_answers'])->firstWhere('key', 'value_proposition');
        $this->assertNotNull($first['question']);
        $this->assertNotNull($first['answer']);
        // الدرجة محسوبة مسبقًا فلا يعيد أحد حسابها.
        $this->assertArrayHasKey('score', $package['deterministic_score']);
    }

    #[Test]
    public function importing_a_valid_payload_builds_the_same_report_marked_as_human_reviewed(): void
    {
        [$user, $run] = $this->completedRun();
        $admin = User::factory()->create(['is_admin' => true]);

        $report = app(ManualReportService::class)->import($run, $this->payload(), $admin);

        // نفس البنية: نتائج وتوصيات وخطوة تالية ودرجة.
        $this->assertGreaterThan(0, $report->findings()->count());
        $this->assertGreaterThan(0, $report->recommendations()->count());
        $this->assertNotNull($report->score);

        // موثّق أنه بشري.
        $this->assertSame('manual', $report->review_mode);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertSame(ToolRun::STATUS_COMPLETED, $run->fresh()->status);

        // بيان صادق موجز في الأعلى + توقيع في الأسفل يوضحان نوع المراجعة
        // بلا مدح ولا ادّعاء غير قابل للإثبات.
        $this->actingAs($user)
            ->get(route('app.reports.show', $report->id))
            ->assertOk()
            ->assertSee('مراجعة يدوية بواسطة خالد سعد')
            ->assertSee('راجعه وكتبه بنفسه');
    }

    #[Test]
    public function an_automatic_report_makes_no_human_review_claim(): void
    {
        [$user, $run] = $this->completedRun();

        // نفس التركيب لكن بلا مراجعة بشرية: مخرج تلقائي.
        $report = app(ManualReportService::class)->import($run, $this->payload(), User::factory()->create(['is_admin' => true]));
        $report->forceFill(['review_mode' => 'auto', 'reviewed_by' => null, 'reviewed_at' => null])->save();

        // العميل يجب أن يميّز: التلقائي لا يدّعي أن إنسانًا راجعه.
        $this->actingAs($user)
            ->get(route('app.reports.show', $report->id))
            ->assertOk()
            ->assertDontSee('مراجعة يدوية بواسطة خالد سعد')
            ->assertDontSee('راجعه وكتبه بنفسه');
    }

    #[Test]
    public function a_payload_that_breaks_the_schema_is_rejected_before_reaching_the_customer(): void
    {
        [, $run] = $this->completedRun();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->expectExceptionMessage('لا يطابق المخطط');

        // نتيجة واحدة فقط بينما المخطط يطلب ثلاثًا على الأقل.
        $broken = $this->payload();
        $broken['findings'] = [$broken['findings'][0]];

        app(ManualReportService::class)->import($run, $broken, $admin);
    }

    /**
     * @return array{0: User, 1: ToolRun}
     */
    private function completedRun(): array
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

        return [$user, $run->fresh()];
    }

    /**
     * مخرج مطابق للمخطط، كما تعيده أداة خارجية.
     *
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
