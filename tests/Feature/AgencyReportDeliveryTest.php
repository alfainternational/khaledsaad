<?php

namespace Tests\Feature;

use App\Models\AgencyReport;
use App\Models\Finding;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Reporting\AgencyReportService;
use App\Services\Projects\ProjectService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgencyReportDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function the_agency_documents_declare_the_shared_print_layout_contract(): void
    {
        foreach (['pdf', 'owner-pdf'] as $view) {
            $pdf = file_get_contents(resource_path("views/agency-reports/{$view}.blade.php"));

            foreach (['class="print-report"', 'print-section', 'print-section--long', 'print-table', 'break-inside: avoid', 'table-layout: fixed', 'overflow-wrap: anywhere'] as $contract) {
                $this->assertStringContainsString($contract, $pdf, "{$view} يفتقد: {$contract}");
            }
        }

        foreach (['document', 'owner-document'] as $view) {
            $document = file_get_contents(resource_path("views/agency-reports/partials/{$view}.blade.php"));

            foreach (['print-report', 'print-section', 'print-section--long', 'print-table'] as $contract) {
                $this->assertStringContainsString($contract, $document, "{$view} يفتقد: {$contract}");
            }
        }
    }

    #[Test]
    public function the_owner_can_generate_view_and_download_the_agency_report(): void
    {
        [$user, $project] = $this->readyProject();

        $this->actingAs($user)
            ->get(route('app.projects.agency-reports.index', $project))
            ->assertOk()
            ->assertSee('جاهز لإنشاء تقريرك الكامل')
            ->assertSee('مكتمل: آخر موعد لاستقبال العروض');

        $response = $this->actingAs($user)
            ->post(route('app.projects.agency-reports.store', $project), [
                'visibility' => [
                    'budget' => 'full',
                    'competitors' => 'summary',
                    'evidence' => 'full',
                ],
            ]);

        $report = AgencyReport::firstOrFail();
        $response->assertRedirect(route('app.agency-reports.show', $report));

        $this->actingAs($user)
            ->get(route('app.agency-reports.show', $report))
            ->assertOk()
            ->assertSee($report->title)
            ->assertSee('خطة عملك الشخصية خلال 30 و60 و90 يومًا')
            ->assertSee('أسئلة مقارنة عروض الوكالات');

        $this->actingAs($user)
            ->get(route('app.agency-reports.pdf', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('app.agency-reports.brief', $report))
            ->assertOk()
            ->assertSee('ما يجب أن يتضمنه عرضكم');

        $this->actingAs($user)
            ->get(route('app.agency-reports.brief.pdf', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function the_api_has_the_same_contract_and_other_users_cannot_read_it(): void
    {
        [$user, $project] = $this->readyProject();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.projects.agency-reports.index', $project))
            ->assertOk()
            ->assertJsonPath('data.readiness.can_generate', true);

        $this->postJson(route('api.v1.projects.agency-reports.store', $project), [
            'visibility' => ['budget' => 'summary', 'competitors' => 'summary', 'evidence' => 'summary'],
        ])->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonStructure(['data' => ['uuid', 'title', 'snapshot']]);

        $report = AgencyReport::firstOrFail();
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->getJson(route('api.v1.agency-reports.show', $report))->assertNotFound();
        $this->getJson(route('api.v1.agency-reports.pdf', $report))->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function readyProject(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع جاهز للوكالة',
            'industry' => 'خدمات',
        ]);
        $project->profile()->updateOrCreate([], [
            'description' => 'خدمة للشركات الصغيرة.',
            'geography' => 'السعودية',
            'monthly_budget' => 9000,
            'primary_goal' => 'leads',
            'value_proposition' => 'تنفيذ أسرع وقياس أوضح.',
        ]);
        app(AgencyReportService::class)->saveBrief($project, [
            'services' => ['ads'],
            'primary_goal' => 'leads',
            'success_metric' => '30 عميلًا مهتمًا مؤهلًا خلال 90 يومًا.',
            'budget_includes_agency_fee' => 'yes',
            'budget_currency' => 'SAR',
            'account_ownership' => 'mine',
            'proposal_deadline' => '15 أغسطس 2026',
        ]);

        foreach (['marketing-score' => 68, 'brand-clarity' => 72, 'audience-map' => 61] as $key => $score) {
            $tool = Tool::where('key', $key)->firstOrFail();
            $run = $project->runs()->create([
                'tool_version_id' => $tool->current_version_id,
                'user_id' => $user->id,
                'status' => ToolRun::STATUS_COMPLETED,
                'base_score' => $score,
            ]);

            $report = Report::create([
                'tool_run_id' => $run->id,
                'project_id' => $project->id,
                'title' => "تقرير {$tool->title}",
                'status' => 'published',
                'score' => $score,
                'score_band' => Report::bandFor($score),
                'summary' => 'ملخص قابل للتسليم.',
            ]);

            $finding = Finding::create([
                'report_id' => $report->id,
                'category' => 'القياس',
                'title' => 'نتيجة قابلة للتنفيذ',
                'description' => 'وصف واضح.',
                'severity' => 'high',
                'evidence' => 'إجابة المستخدم.',
                'confidence' => 90,
                'is_assumption' => false,
                'sort_order' => 0,
            ]);

            Recommendation::create([
                'finding_id' => $finding->id,
                'report_id' => $report->id,
                'title' => "نفّذ أولوية {$key}",
                'description' => 'خطوة محددة بمالك وموعد.',
                'impact' => 'high',
                'effort' => 'low',
                'priority' => 90,
            ]);
        }

        return [$user, $project];
    }
}
