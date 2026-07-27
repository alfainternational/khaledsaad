<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectKnowledgeService;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportService;
use App\Services\Reports\ReportFreshnessService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgencyReportFreshnessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function knowledge_changes_mark_old_reports_stale_in_service_api_and_web_without_mutating_the_snapshot(): void
    {
        $this->seed(ToolCatalogSeeder::class);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الحداثة']);
        foreach (['marketing-score', 'brand-clarity', 'audience-map'] as $tool) {
            $this->report($project, $user, $tool);
        }
        $agencyReport = app(AgencyReportService::class)->generate($project, $user);
        $frozen = $agencyReport->snapshot;

        $this->assertFalse(app(ReportFreshnessService::class)->status($agencyReport)['is_stale']);

        $this->travel(2)->minutes();
        app(ProjectKnowledgeService::class)->record(
            $project,
            'value_proposition',
            'قيمة جديدة بعد إصدار التقرير',
            'profile',
        );

        $freshness = app(ReportFreshnessService::class)->status($agencyReport->fresh());
        $this->assertTrue($freshness['is_stale']);
        $this->assertSame('stale', $freshness['state']);
        $this->assertContains('تغيرت معلومات المشروع بعد إنشاء هذا الإصدار.', $freshness['reasons']);
        $this->assertSame($frozen, $agencyReport->fresh()->snapshot);

        Sanctum::actingAs($user);
        $this->getJson(route('api.v1.agency-reports.show', $agencyReport))
            ->assertOk()
            ->assertJsonPath('data.freshness.is_stale', true)
            ->assertJsonPath('data.freshness.state', 'stale');

        $this->actingAs($user)->get(route('app.agency-reports.show', $agencyReport))
            ->assertOk()
            ->assertSee('هذا الإصدار يحتاج تحديثًا')
            ->assertSee('أنشئ إصدارًا محدثًا');
        $this->actingAs($user)->get(route('app.projects.agency-reports.index', $project))
            ->assertOk()
            ->assertSee('يحتاج تحديثًا');
    }

    private function report($project, User $user, string $toolKey): void
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
        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => "نتيجة {$toolKey}",
            'description' => 'وصف النتيجة.',
            'severity' => 'high',
            'evidence' => 'إجابة المستخدم',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);
        $finding->recommendations()->create([
            'report_id' => $report->id,
            'title' => "أولوية {$toolKey}",
            'description' => 'خطوة قابلة للتنفيذ.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 90,
            'kpi_hint' => 'معدل التحويل',
        ]);
    }
}
