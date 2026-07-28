<?php

namespace Tests\Feature;

use App\Models\ConsultationInference;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Intake\ConsultationService;
use App\Modules\Reporting\AgencyReportService;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ProjectSnapshotBuilder;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedDiagnosticSynthesisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_unified_report_contains_sourced_consultation_context_and_cross_tool_results(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع التركيب']);
        $marketing = $this->report($project, $user, 'marketing-score', 'high');
        $brand = $this->report($project, $user, 'brand-clarity', 'low');
        $this->report($project, $user, 'audience-map', 'high');

        $session = app(ConsultationService::class)->start($project, $user);
        ConsultationInference::create([
            'consultation_session_id' => $session->id,
            'key' => 'assumption.retention',
            'type' => 'assumption',
            'statement' => 'الاحتفاظ ضعيف ويحتاج قياسًا.',
            'confidence' => 45,
            'status' => 'provisional',
        ]);
        $session->conflicts()->create([
            'key' => 'sales-period',
            'severity' => 'high',
            'message' => 'ورد رقمان لفترتين مختلفتين.',
            'subject' => ['monthly_sales'],
            'status' => 'resolved',
            'resolution' => ['statement' => 'يعتمد رقم آخر ثلاثين يومًا.', 'source' => 'user'],
            'resolved_at' => now(),
        ]);
        $session->evidence()->create([
            'type' => 'uploaded_file',
            'source_label' => 'sales.txt',
            'source_locator' => 'consultations/proof.txt',
            'disk' => 'local',
            'mime_type' => 'text/plain',
            'size_bytes' => 120,
            'extraction_status' => 'completed',
            'extracted_text' => 'سجل مبيعات موثق لآخر ثلاثين يومًا.',
            'sha256' => str_repeat('a', 64),
            'confidence' => 'high',
            'observed_at' => now(),
        ]);

        $agencyReport = app(AgencyReportService::class)->generate($project, $user, [], $session);
        $snapshot = $agencyReport->snapshot;

        $this->assertSame($session->id, $agencyReport->consultation_session_id);
        $this->assertSame($session->uuid, data_get($snapshot, 'consultation.uuid'));
        $this->assertSame('الاحتفاظ ضعيف ويحتاج قياسًا.', data_get($snapshot, 'consultation.inferences.0.statement'));
        $this->assertSame('يعتمد رقم آخر ثلاثين يومًا.', data_get($snapshot, 'consultation.conflicts.0.resolution.statement'));
        $this->assertStringContainsString('سجل مبيعات', (string) data_get($snapshot, 'consultation.evidence.0.text'));
        $this->assertContains($marketing->id, data_get($snapshot, 'cross_tool_synthesis.source_report_ids'));
        $this->assertContains($brand->id, data_get($snapshot, 'cross_tool_synthesis.source_report_ids'));
        $this->assertNotEmpty(data_get($snapshot, 'cross_tool_synthesis.divergences'));
        foreach (data_get($snapshot, 'cross_tool_synthesis.findings') as $finding) {
            $this->assertArrayHasKey('source_report_id', $finding);
            $this->assertArrayHasKey('source_tool_key', $finding);
        }

        $private = app(AgencyReportService::class)->generate(
            $project,
            $user,
            ['evidence' => 'private'],
            $session,
        );
        $privateJson = json_encode($private->snapshot, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('سجل مبيعات موثق', $privateJson);
        $this->assertStringContainsString('إجابة المستخدم', $privateJson);
        $agencyJson = json_encode($private->snapshot['agency_brief'], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('سجل مبيعات موثق', $agencyJson);
        $this->assertStringNotContainsString('إجابة المستخدم', $agencyJson);
    }

    #[Test]
    public function a_later_tool_snapshot_receives_prior_published_tool_results_with_sources(): void
    {
        $this->seed(ToolCatalogSeeder::class);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'ذاكرة النتائج']);
        $report = $this->report($project, $user, 'marketing-score', 'high');
        $tool = Tool::where('key', 'content-engine')->firstOrFail();
        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user->id,
            'status' => ToolRun::STATUS_DRAFT,
        ]);

        $snapshot = app(ProjectSnapshotBuilder::class)->build(
            $run->load(['project', 'toolVersion', 'answers', 'files']),
        );

        $this->assertSame($report->id, data_get($snapshot, 'prior_diagnostic_results.0.source_report_id'));
        $this->assertSame('marketing-score', data_get($snapshot, 'prior_diagnostic_results.0.source_tool_key'));
        $this->assertSame('مشكلة القياس', data_get($snapshot, 'prior_diagnostic_results.0.findings.0.title'));
    }

    private function report($project, User $user, string $toolKey, string $severity): Report
    {
        $tool = Tool::where('key', $toolKey)->firstOrFail();
        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user->id,
            'status' => ToolRun::STATUS_COMPLETED,
            'base_score' => 60,
        ]);
        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => "تقرير {$tool->title}",
            'status' => 'published',
            'score' => 60,
            'score_band' => Report::bandFor(60),
            'summary' => "ملخص {$tool->title}",
            'assumptions' => [],
        ]);
        Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => 'مشكلة القياس',
            'description' => "نتيجة من {$toolKey}",
            'severity' => $severity,
            'evidence' => 'إجابة المستخدم',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);
        $finding = $report->findings()->firstOrFail();
        $finding->recommendations()->create([
            'report_id' => $report->id,
            'title' => "أولوية {$toolKey}",
            'description' => 'خطوة قابلة للتنفيذ.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 90,
            'kpi_hint' => 'معدل التحويل',
        ]);

        return $report;
    }
}
