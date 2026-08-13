<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Reporting\ReportCharts;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Experience\Experience;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الرسوم البيانية للتقرير: مصدر واحد يغذي الويب والتطبيق والـPDF.
 */
class ReportChartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function charts_are_built_from_report_data(): void
    {
        $report = $this->report();

        $charts = app(ReportCharts::class)->build($report);

        $this->assertSame(62, $charts['score_gauge']['value']);
        $this->assertSame('مستقر', $charts['score_gauge']['band']);

        $severity = collect($charts['severity_distribution']['items'])->keyBy('key');
        $this->assertSame(1, $severity['high']['count']);

        $evidence = collect($charts['evidence_split']['items'])->keyBy('key');
        $this->assertSame(1, $evidence['evidence']['count']);
        $this->assertSame(0, $evidence['assumption']['count']);

        // توصية واحدة بأثر عالٍ وجهد بسيط = مكسب سريع.
        $this->assertSame(1, $charts['impact_effort']['quick_wins']);

        // تقرير واحد فقط: لا مخطط تطور بعد.
        $this->assertNull($charts['score_history']);
    }

    #[Test]
    public function the_api_report_includes_charts_for_the_mobile_app(): void
    {
        $report = $this->report();
        $owner = $report->project->workspace->owner;

        $this->actingAs($owner)
            ->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.charts.score_gauge.value', 62)
            ->assertJsonStructure([
                'data' => [
                    'charts' => ['score_gauge', 'score_history', 'severity_distribution', 'evidence_split', 'impact_effort'],
                ],
            ]);
    }

    #[Test]
    public function score_history_appears_once_a_second_report_exists(): void
    {
        $report = $this->report();

        $olderRun = app(ToolRunService::class)->start($report->project, Tool::where('key', 'marketing-score')->firstOrFail(), $report->project->workspace->owner);
        $olderRun->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 40])->save();

        Report::create([
            'tool_run_id' => $olderRun->id,
            'project_id' => $report->project_id,
            'title' => 'تقرير سابق',
            'status' => 'published',
            'score' => 40,
            'score_band' => 'يحتاج ترتيبًا',
            'summary' => 'ملخص.',
        ])->forceFill(['created_at' => now()->subDays(15)])->save();

        $charts = app(ReportCharts::class)->build($report->fresh());

        $this->assertNotNull($charts['score_history']);
        $this->assertSame([40, 62], array_column($charts['score_history']['points'], 'value'));
        $this->assertTrue(end($charts['score_history']['points'])['is_current']);
    }

    private function report(): Report
    {
        $user = User::factory()->create([
            'active_experience' => Experience::BUSINESS,
            'business_experience_enabled_at' => now(),
        ]);
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الرسوم']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 62])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير الرسوم',
            'status' => 'published',
            'score' => 62,
            'score_band' => 'مستقر',
            'summary' => 'ملخص.',
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => 'القياس ناقص',
            'description' => 'لا يوجد تتبع.',
            'severity' => 'high',
            'evidence' => 'إجابة المستخدم',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => 'ثبّت أداة تحليلات',
            'description' => 'أضف قياسًا.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 80,
        ]);

        return $report->fresh();
    }
}
