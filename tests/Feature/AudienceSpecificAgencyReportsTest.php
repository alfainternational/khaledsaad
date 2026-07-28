<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QuestionDefinition;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Consultations\ConsultationService;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportDocumentAdapter;
use App\Services\Reports\AgencyReportService;
use App\Services\Reports\AgencyReportSharing;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AudienceSpecificAgencyReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
        $this->withoutVite();
    }

    #[Test]
    public function the_agency_brief_gate_requires_exactly_six_business_decisions(): void
    {
        [, $project] = $this->project();
        $service = app(AgencyReportService::class);

        $empty = $service->briefCompleteness($project);

        $this->assertCount(6, $empty['requirements']);
        $this->assertSame(6, $empty['missing_count']);
        $this->assertFalse($empty['is_ready']);
        $this->assertSame(
            ['success_metric', 'primary_goal', 'budget_terms', 'services', 'account_ownership', 'proposal_deadline'],
            array_column($empty['requirements'], 'key'),
        );

        $service->saveBrief($project->fresh(), [
            'services' => ['ads'],
            'primary_goal' => 'sales',
            'success_metric' => '20 عملية شراء مدفوعة خلال 90 يومًا.',
            'budget_includes_agency_fee' => 'yes',
            'budget_currency' => 'SAR',
            'account_ownership' => 'mine',
            'proposal_deadline' => '2026-08-15',
            'proposal_submission' => 'ترسل العروض عبر البريد المسجل في المشروع.',
        ]);

        $complete = $service->briefCompleteness($project->fresh());

        $this->assertTrue($complete['is_ready']);
        $this->assertTrue($complete['is_quotable']);
        $this->assertSame(0, $complete['missing_count']);
        $this->assertSame([], $complete['missing_critical']);
    }

    #[Test]
    public function the_snapshot_contains_a_complete_owner_report_and_a_separate_agency_brief(): void
    {
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);

        $snapshot = app(AgencyReportService::class)->generate($project->fresh(), $user)->snapshot;

        $this->assertSame([
            'overview',
            'numbers',
            'journey',
            'problems',
            'conflicts',
            'unknowns',
            'this_week',
            'before_agency',
            'readiness',
            'private_details',
        ], array_keys($snapshot['owner_report']));
        $this->assertSame([
            'project',
            'baseline',
            'goal',
            'scope',
            'assets',
            'workflow',
            'terms',
            'proposal',
            'submission',
            'readiness',
        ], array_keys($snapshot['agency_brief']));
        $this->assertTrue($snapshot['agency_brief']['readiness']['is_ready']);
        foreach (['project', 'audiences', 'assets', 'tools', 'competitors', 'evidence', 'kpis', 'consultation', 'assumptions', 'appendix', 'behaviour', 'plan', 'methodology'] as $required) {
            $this->assertArrayHasKey($required, $snapshot['owner_report']['private_details']);
        }
        foreach (['previous_attempts', 'previous_provider', 'current_customer_source', 'kpis'] as $required) {
            $this->assertArrayHasKey($required, $snapshot['agency_brief']['baseline']);
        }
        $this->assertArrayHasKey('budget', $snapshot['agency_brief']['proposal']);
    }

    #[Test]
    public function the_owner_page_is_a_complete_human_report_without_the_agency_document_body(): void
    {
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        $this->actingAs($user)
            ->get(route('app.agency-reports.show', $report))
            ->assertOk()
            ->assertSee('أين يقف مشروعك الآن؟')
            ->assertSee('أرقامك ببساطة')
            ->assertSee('صورة مشروعك الكاملة')
            ->assertSee('عملاؤك كما نفهمهم الآن')
            ->assertSee('ما لديك جاهز وما يحتاج تجهيزًا')
            ->assertSee('ماذا قالت كل التشخيصات؟')
            ->assertSee('المؤشرات التي تتابعها')
            ->assertSee('المنافسون والمعلومات التي بُني عليها التقرير')
            ->assertSee('أين يتوقف الناس؟')
            ->assertSee('أهم ثلاث مشكلات')
            ->assertSee('أمور تحتاج أن تحسمها')
            ->assertSee('ما الذي ما زلنا لا نعرفه؟')
            ->assertSee('ما يمكنك فعله هذا الأسبوع')
            ->assertSee('قبل أن تتحدث مع أي وكالة')
            ->assertSee('هل أصبح موجز الوكالة جاهزًا؟')
            ->assertDontSee('ما يجب أن يتضمنه عرضكم')
            ->assertDontSee('المستند كما تراه الوكالة');
    }

    #[Test]
    public function the_owner_page_renders_multi_choice_consultation_answers_as_human_text(): void
    {
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        $snapshot = $report->snapshot;
        $snapshot['owner_report']['private_details']['consultation'] = [
            'answers' => [[
                'question' => 'ما القنوات التي تستخدمها؟',
                'value' => ['الإعلانات المدفوعة', 'المحتوى العضوي'],
                'is_unknown' => false,
            ]],
            'inferences' => [],
            'evidence' => [],
        ];
        $report->forceFill(['snapshot' => $snapshot])->save();

        $this->actingAs($user)
            ->get(route('app.agency-reports.show', $report))
            ->assertOk()
            ->assertSee('الإعلانات المدفوعة، المحتوى العضوي');
    }

    #[Test]
    public function the_owner_page_uses_arabic_labels_instead_of_internal_option_codes(): void
    {
        $this->seed(ConsultationCatalogSeeder::class);
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);

        $session = app(ConsultationService::class)->start($project, $user);
        $question = QuestionDefinition::query()
            ->where('internal_variable', 'competitor_research_depth')
            ->firstOrFail()
            ->versions()
            ->firstOrFail();
        $session->answers()->create([
            'question_version_id' => $question->id,
            'value_json' => ['value' => 'browsed'],
        ]);
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user, [], $session->fresh());

        $this->assertSame($session->id, $report->consultation_session_id);
        $this->assertSame(
            'browsed',
            data_get($report->snapshot, 'owner_report.private_details.consultation.answers.0.value'),
        );
        $presented = app(AgencyReportDocumentAdapter::class)->ownerSnapshot($report);

        $this->assertSame(
            'تصفّحت مواقعهم وحساباتهم',
            data_get($presented, 'owner_report.private_details.consultation.answers.0.display_value'),
        );

        $this->actingAs($user)
            ->get(route('app.agency-reports.show', $report))
            ->assertOk()
            ->assertSee('تصفّحت مواقعهم وحساباتهم')
            ->assertDontSee('browsed');

        Sanctum::actingAs($user);
        $this->getJson(route('api.v1.agency-reports.show', $report))
            ->assertOk()
            ->assertJsonPath(
                'data.snapshot.owner_report.private_details.consultation.answers.0.display_value',
                'تصفّحت مواقعهم وحساباتهم',
            );
    }

    #[Test]
    public function the_agency_brief_page_contains_only_information_the_agency_needs(): void
    {
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        $this->actingAs($user)
            ->get(route('app.agency-reports.brief', $report))
            ->assertOk()
            ->assertSee('المشروع في سطور واضحة')
            ->assertSee('خط الأساس')
            ->assertSee('الهدف الذي سنعمل عليه')
            ->assertSee('النطاق المطلوب')
            ->assertSee('الأصول والوصول')
            ->assertSee('آلية العمل')
            ->assertSee('الملكية وشروط الانتهاء')
            ->assertSee('ما يجب أن يتضمنه عرضكم')
            ->assertSee('موعد وطريقة تسليم العرض')
            ->assertSee('الميزانية التي سيُبنى عليها العرض')
            ->assertSee('ما جُرّب سابقًا')
            ->assertSee('مصدر العملاء الحالي')
            ->assertDontSee('سجل التنفيذ')
            ->assertDontSee('درجة الثقة')
            ->assertDontSee('افتراض')
            ->assertDontSee('مقارنة نتائج التشخيصات')
            ->assertDontSee('أسماء الأدوات');
    }

    #[Test]
    public function an_incomplete_agency_brief_cannot_be_shared_and_a_complete_one_uses_an_allow_list(): void
    {
        [$user, $project] = $this->project();
        $this->completeCoreReports($project, $user);
        $incomplete = app(AgencyReportService::class)->generate($project->fresh(), $user);

        $this->actingAs($user)
            ->post(route('app.agency-reports.share', $incomplete), ['days' => 30])
            ->assertSessionHasErrors('brief');
        $this->assertNull($incomplete->fresh()->share_token);

        $this->completeBrief($project);
        $complete = app(AgencyReportService::class)->generate($project->fresh(), $user);
        $sharing = app(AgencyReportSharing::class);
        $sharing->share($complete, 30);

        $sharedSnapshot = $sharing->dataFile($complete->fresh())['snapshot'];

        $this->assertSame(['agency_brief'], array_keys($sharedSnapshot));
        foreach (['owner_report', 'owner_guide', 'behaviour', 'cross_tool_synthesis', 'methodology', 'tools', 'assumptions'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $sharedSnapshot);
        }
    }

    #[Test]
    public function the_two_pdf_templates_keep_the_same_audience_boundary(): void
    {
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        $owner = view('agency-reports.owner-pdf', [
            'agencyReport' => $report,
            'snapshot' => $report->snapshot,
            'brand' => config('brand'),
        ])->render();
        $agency = view('agency-reports.pdf', [
            'agencyReport' => $report,
            'snapshot' => ['agency_brief' => $report->snapshot['agency_brief']],
            'brand' => config('brand'),
        ])->render();

        $this->assertStringContainsString('أين يقف مشروعك الآن؟', $owner);
        $this->assertStringContainsString('قبل أن تتحدث مع أي وكالة', $owner);
        $this->assertStringNotContainsString('ما يجب أن يتضمنه عرضكم', $owner);
        $this->assertStringContainsString('ما يجب أن يتضمنه عرضكم', $agency);
        $this->assertStringNotContainsString('سجل التنفيذ', $agency);
        $this->assertStringNotContainsString('درجة الثقة', $agency);
        $this->assertStringNotContainsString('افتراض', $agency);
    }

    #[Test]
    public function the_api_describes_both_documents_and_the_agency_gate_for_the_app(): void
    {
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.agency-reports.show', $report))
            ->assertOk()
            ->assertJsonPath('data.documents.owner.label', 'تقريرك الكامل')
            ->assertJsonPath('data.documents.agency_brief.label', 'موجز التكليف للوكالة')
            ->assertJsonPath('data.documents.agency_brief.is_ready', true)
            ->assertJsonPath('data.documents.agency_brief.missing_count', 0)
            ->assertJsonPath('data.documents.agency_brief.pdf_url', route('api.v1.agency-reports.brief.pdf', $report));
    }

    #[Test]
    public function reports_and_live_links_created_before_the_two_document_format_still_open(): void
    {
        [$user, $project] = $this->project();
        $this->completeBrief($project);
        $this->completeCoreReports($project, $user);
        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        $legacy = $report->snapshot;
        unset($legacy['owner_report'], $legacy['agency_brief']);
        $report->forceFill([
            'snapshot' => $legacy,
            'share_token' => 'legacy-live-token',
            'share_created_at' => now(),
            'share_expires_at' => now()->addDay(),
        ])->save();

        $this->actingAs($user)
            ->get(route('app.agency-reports.show', $report))
            ->assertOk()
            ->assertSee('هذا إصدار سابق');

        Sanctum::actingAs($user);
        $this->getJson(route('api.v1.agency-reports.show', $report))
            ->assertOk()
            ->assertJsonStructure(['data' => ['snapshot' => ['owner_report']]]);

        $this->get(route('shared.agency-report', 'legacy-live-token'))
            ->assertOk()
            ->assertSee('هذه نسخة قديمة محفوظة كما كانت وقت المشاركة');
    }

    /** @return array{0: User, 1: Project} */
    private function project(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع الاختبار',
            'industry' => 'التجارة الإلكترونية',
            'geography' => 'السعودية',
            'monthly_budget' => 5000,
        ]);

        return [$user, $project];
    }

    private function completeCoreReports(Project $project, User $user): void
    {
        foreach (['marketing-score', 'brand-clarity', 'audience-map'] as $key) {
            $tool = Tool::where('key', $key)->firstOrFail();
            $run = $project->runs()->create([
                'tool_version_id' => $tool->current_version_id,
                'user_id' => $user->id,
                'status' => ToolRun::STATUS_COMPLETED,
                'base_score' => 65,
            ]);

            Report::create([
                'tool_run_id' => $run->id,
                'project_id' => $project->id,
                'title' => "تقرير {$tool->title}",
                'status' => 'published',
                'score' => 65,
                'score_band' => Report::bandFor(65),
                'summary' => 'شرح واضح لحالة المشروع وما يحتاجه الآن.',
            ]);
        }
    }

    private function completeBrief(Project $project): void
    {
        $project->profile()->update([
            'primary_goal' => 'sales',
            'description' => 'متجر يبيع منتجات محلية عبر الإنترنت.',
            'value_proposition' => 'منتجات موثوقة تصل بسرعة.',
        ]);

        app(AgencyReportService::class)->saveBrief($project->fresh(), [
            'services' => ['ads'],
            'success_metric' => '20 عملية شراء مدفوعة خلال 90 يومًا.',
            'budget_includes_agency_fee' => 'yes',
            'budget_currency' => 'SAR',
            'account_ownership' => 'mine',
            'decision_maker' => 'صاحب المشروع',
            'approval_time' => 'same_day',
            'lead_response_owner' => 'فريق المبيعات خلال ساعة',
            'proposal_deadline' => '15 أغسطس 2026، الساعة 5 مساءً',
            'proposal_submission' => 'ملف PDF على البريد المسجل في المشروع.',
        ]);
    }
}
