<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Consultations\ConsultationService;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportService;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedDiagnosticPresentationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function web_and_pdf_show_the_same_sourced_consultation_and_cross_tool_sections(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع العرض']);
        foreach (['marketing-score', 'brand-clarity', 'audience-map'] as $index => $tool) {
            $this->report($project, $user, $tool, $index === 1 ? 'low' : 'high');
        }
        $session = app(ConsultationService::class)->start($project, $user);
        $session->inferences()->create([
            'key' => 'assumption.measurement', 'type' => 'assumption',
            'statement' => 'افتراض يحتاج تحققًا من المبيعات.', 'confidence' => 40, 'status' => 'provisional',
        ]);
        $session->conflicts()->create([
            'key' => 'period', 'severity' => 'high', 'message' => 'فترتان مختلفتان.',
            'subject' => ['sales'], 'status' => 'resolved',
            'resolution' => ['statement' => 'اعتمدت آخر ثلاثين يومًا.', 'source' => 'user'], 'resolved_at' => now(),
        ]);
        $session->evidence()->create([
            'type' => 'uploaded_file', 'source_label' => 'sales.txt', 'source_locator' => 'proof.txt',
            'disk' => 'local', 'mime_type' => 'text/plain', 'size_bytes' => 100,
            'extraction_status' => 'completed', 'extracted_text' => 'دليل المبيعات السري الكامل.',
            'sha256' => str_repeat('b', 64), 'confidence' => 'high', 'observed_at' => now(),
        ]);

        $full = app(AgencyReportService::class)->generate($project, $user, ['evidence' => 'full'], $session);
        $web = view('agency-reports.partials.owner-document', ['snapshot' => $full->snapshot])->render();
        $pdf = view('agency-reports.owner-pdf', [
            'agencyReport' => $full, 'snapshot' => $full->snapshot, 'brand' => config('brand'),
        ])->render();

        foreach ([$web, $pdf] as $html) {
            $this->assertStringContainsString('ما سجلته في التشخيص الذكي', $html);
            $this->assertStringContainsString('افتراض يحتاج تحققًا من المبيعات', $html);
            $this->assertStringContainsString('اعتمدت آخر ثلاثين يومًا', $html);
            $this->assertStringContainsString('دليل المبيعات السري الكامل', $html);
            $this->assertStringContainsString('مقارنة نتائج التشخيصات', $html);
            $this->assertStringContainsString('اختلاف يحتاج حسمًا', $html);
        }

        $private = app(AgencyReportService::class)->generate($project, $user, ['evidence' => 'private'], $session);
        $privatePdf = view('agency-reports.owner-pdf', [
            'agencyReport' => $private, 'snapshot' => $private->snapshot, 'brand' => config('brand'),
        ])->render();
        $this->assertStringContainsString('دليل المبيعات السري الكامل', $privatePdf);

        $agencyPdf = view('agency-reports.pdf', [
            'agencyReport' => $private,
            'snapshot' => ['agency_brief' => $private->snapshot['agency_brief']],
            'brand' => config('brand'),
        ])->render();
        $this->assertStringNotContainsString('دليل المبيعات السري الكامل', $agencyPdf);
        $this->assertStringNotContainsString('ما سجلته في التشخيص الذكي', $agencyPdf);
    }

    private function report($project, User $user, string $toolKey, string $severity): void
    {
        $tool = Tool::where('key', $toolKey)->firstOrFail();
        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id, 'user_id' => $user->id,
            'status' => ToolRun::STATUS_COMPLETED, 'base_score' => 60,
        ]);
        $report = Report::create([
            'tool_run_id' => $run->id, 'project_id' => $project->id,
            'title' => "تقرير {$tool->title}", 'status' => 'published', 'score' => 60,
            'score_band' => Report::bandFor(60), 'summary' => "ملخص {$tool->title}", 'assumptions' => [],
        ]);
        $finding = Finding::create([
            'report_id' => $report->id, 'category' => 'القياس', 'title' => "نتيجة {$toolKey}",
            'description' => 'وصف النتيجة.', 'severity' => $severity, 'evidence' => 'إجابة المستخدم',
            'confidence' => 80, 'is_assumption' => false, 'sort_order' => 0,
        ]);
        $finding->recommendations()->create([
            'report_id' => $report->id, 'title' => "أولوية {$toolKey}",
            'description' => 'خطوة قابلة للتنفيذ.', 'impact' => 'high', 'effort' => 'low',
            'priority' => 90, 'kpi_hint' => 'معدل التحويل',
        ]);
    }
}
