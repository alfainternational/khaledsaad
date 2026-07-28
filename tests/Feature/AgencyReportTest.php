<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgencyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function generation_is_blocked_until_the_three_core_tools_are_complete(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع ناقص']);
        $this->reportFor($project, $user, 'marketing-score', 55, now());

        $readiness = app(AgencyReportService::class)->readiness($project);

        $this->assertFalse($readiness['can_generate']);
        $this->assertSame(['brand-clarity', 'audience-map'], array_column($readiness['missing_core'], 'key'));

        $this->expectException(ValidationException::class);
        app(AgencyReportService::class)->generate($project, $user);
    }

    #[Test]
    public function a_versioned_snapshot_uses_the_latest_report_per_tool_and_never_changes_afterward(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع الوكالة',
            'industry' => 'التجارة الإلكترونية',
        ]);
        $project->profile()->updateOrCreate([], [
            'description' => 'متجر منتجات محلية.',
            'monthly_budget' => 12000,
            'primary_goal' => 'sales',
            'value_proposition' => 'توصيل سريع ومنتجات موثقة.',
        ]);

        $oldMarketing = $this->reportFor($project, $user, 'marketing-score', 40, now()->subDays(40));
        $newMarketing = $this->reportFor($project, $user, 'marketing-score', 65, now()->subDays(5));
        $brand = $this->reportFor($project, $user, 'brand-clarity', 72, now()->subDays(4));
        $audience = $this->reportFor($project, $user, 'audience-map', 58, now()->subDays(3));
        $channel = $this->reportFor($project, $user, 'channel-fit', 80, now()->subDay());

        $agencyReport = app(AgencyReportService::class)->generate($project, $user, [
            'budget' => 'summary',
            'competitors' => 'summary',
            'evidence' => 'private',
        ]);

        $snapshot = $agencyReport->snapshot;

        $this->assertSame(1, $agencyReport->version);
        $this->assertSame(65, $snapshot['readiness']['score']);
        $this->assertEqualsCanonicalizing(
            [$newMarketing->id, $brand->id, $audience->id, $channel->id],
            $agencyReport->source_report_ids,
        );
        $this->assertNotContains($oldMarketing->id, $agencyReport->source_report_ids);
        $this->assertCount(4, $snapshot['tools']);
        $this->assertArrayHasKey('30_days', $snapshot['plan']);
        $this->assertArrayHasKey('90_days', $snapshot['plan']);
        // متطلبات العرض موجَّهة للوكالة؛ وأسئلة المقارنة صارت في دليل المالك وحده.
        $this->assertNotEmpty($snapshot['proposal_requirements']);
        $this->assertNotEmpty($snapshot['owner_guide']['comparison_questions']);
        // تقرير المالك كامل دائمًا؛ الحجب يُطبق لاحقًا بقائمة سماح على موجز الوكالة وحده.
        $this->assertSame(12000, $snapshot['project']['monthly_budget']);
        $this->assertNull($snapshot['project']['budget_summary']);
        $this->assertSame(12000, $snapshot['commercials']['stated_budget']);
        $this->assertNotNull($snapshot['priorities'][0]['evidence']);
        foreach (['root_cause', 'commercial_impact', 'action_steps', 'owner_role', 'resources', 'timeframe', 'dependencies', 'kpi_definition', 'kpi_source', 'success_condition', 'stop_condition', 'risks', 'confidence', 'source_report_id'] as $field) {
            $this->assertArrayHasKey($field, $snapshot['priorities'][0]);
        }
        $this->assertNotEmpty($snapshot['priorities'][0]['action_steps']);
        $this->assertNotEmpty($snapshot['priorities'][0]['missing_baseline_reason']);
        $this->assertStringContainsString(
            'دليل تفصيلي سري',
            json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        );

        $frozenTitle = $snapshot['priorities'][0]['title'];
        Recommendation::where('report_id', $newMarketing->id)->firstOrFail()
            ->update(['title' => 'عنوان تغير بعد التسليم']);

        $this->assertSame($frozenTitle, $agencyReport->fresh()->snapshot['priorities'][0]['title']);

        $this->reportFor($project, $user, 'brand-clarity', 79, now());
        $second = app(AgencyReportService::class)->generate($project, $user);

        $this->assertSame(2, $second->version);
        $this->assertNotSame($agencyReport->uuid, $second->uuid);
    }

    private function reportFor(
        Project $project,
        User $user,
        string $toolKey,
        int $score,
        \DateTimeInterface $createdAt,
    ): Report {
        $tool = Tool::where('key', $toolKey)->firstOrFail();
        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user->id,
            'status' => ToolRun::STATUS_COMPLETED,
            'base_score' => $score,
            'snapshot' => ['profile' => ['monthly_budget' => 12000]],
        ]);
        $run->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => "تقرير {$tool->title}",
            'status' => 'published',
            'score' => $score,
            'score_band' => Report::bandFor($score),
            'summary' => "ملخص {$tool->title}.",
            'assumptions' => ['افتراض يحتاج تحققًا.'],
        ]);
        $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        $report->sections()->create([
            'key' => 'analysis',
            'title' => 'تحليل',
            'sort_order' => 0,
            'content_json' => [
                'headline' => 'خلاصة القسم',
                'points' => [
                    ['text' => 'نقطة', 'evidence' => 'دليل تفصيلي سري'],
                ],
            ],
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => "نتيجة {$toolKey}",
            'description' => 'وصف النتيجة.',
            'severity' => 'high',
            'evidence' => 'إجابة موثقة من المستخدم.',
            'confidence' => 85,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => "أولوية {$toolKey}",
            'description' => 'خطوة تنفيذية قابلة للقياس.',
            'impact' => 'high',
            'effort' => 'low',
            'priority' => 90,
            'kpi_hint' => 'معدل التحويل',
        ]);

        return $report;
    }
}
