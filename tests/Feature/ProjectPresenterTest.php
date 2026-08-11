<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Support\Presentation\ProjectPresenter;
use App\Support\Presentation\ReportPresenter;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectPresenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function overview_compares_the_latest_report_with_the_previous_report_for_the_same_tool(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المقارنة']);

        $this->reportFor($project, $user, 'marketing-score', 40, now()->subDays(30));
        $this->reportFor($project, $user, 'brand-clarity', 90, now()->subDays(15));
        $this->reportFor($project, $user, 'marketing-score', 60, now());

        $overview = app(ProjectPresenter::class)->overview($project->fresh());

        $this->assertSame(40, $overview['comparison']['previous_score']);
        $this->assertSame(60, $overview['comparison']['current_score']);
        $this->assertSame(20, $overview['comparison']['delta']);
        $this->assertSame('up', $overview['comparison']['direction']);
    }

    #[Test]
    public function overview_groups_repeated_reports_by_diagnosis_without_losing_history(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع التاريخ']);

        $older = $this->reportFor($project, $user, 'marketing-score', 40, now()->subMonth());
        $latest = $this->reportFor($project, $user, 'marketing-score', 65, now());
        $other = $this->reportFor($project, $user, 'brand-clarity', 80, now()->subWeek());

        $groups = app(ProjectPresenter::class)->overview($project->fresh())['report_groups'];

        $this->assertCount(2, $groups);
        $marketing = collect($groups)->firstWhere('tool_key', 'marketing-score');
        $this->assertSame($latest->id, $marketing['latest']['id']);
        $this->assertSame(2, $marketing['versions_count']);
        $this->assertSame([$older->id], collect($marketing['history'])->pluck('id')->all());
        $this->assertContains($other->id, collect($groups)->pluck('latest.id')->all());
    }

    #[Test]
    public function report_starts_with_a_compact_executive_decision_summary(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الملخص']);
        $report = $this->reportFor($project, $user, 'marketing-score', 55, now());
        $report->update([
            'summary' => 'الوضع الحالي يحتاج تركيزًا أوضح.',
            'assumptions' => ['حجم الطلب الشهري يحتاج تأكيدًا.'],
            'next_step' => [
                'title' => 'راجع عرضك هذا الأسبوع',
                'description' => 'اكتب وعدًا واحدًا يمكن قياسه.',
            ],
        ]);

        foreach (['مشكلة أولى', 'مشكلة ثانية', 'مشكلة ثالثة', 'مشكلة رابعة'] as $index => $title) {
            $report->findings()->create([
                'category' => 'marketing',
                'title' => $title,
                'description' => $title,
                'severity' => 'medium',
                'evidence' => 'إجابة المستخدم',
                'confidence' => 0.8,
                'is_assumption' => false,
                'sort_order' => $index + 1,
            ]);
        }

        $executive = app(ReportPresenter::class)->full($report->fresh())['executive_summary'];

        $this->assertSame('الوضع الحالي يحتاج تركيزًا أوضح.', $executive['current_state']);
        $this->assertSame(['مشكلة أولى', 'مشكلة ثانية', 'مشكلة ثالثة'], $executive['top_issues']);
        $this->assertSame('راجع عرضك هذا الأسبوع', $executive['this_week']['title']);
        $this->assertSame('حجم الطلب الشهري يحتاج تأكيدًا.', $executive['needs_confirmation']);
    }

    #[Test]
    public function overview_flags_an_unclear_project_description_instead_of_repeating_it(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الوصف']);

        $this->assertTrue(app(ProjectPresenter::class)->overview($project)['description_needs_attention']);

        $project->profile->update([
            'description' => 'خدمة استشارات تسويقية تساعد المتاجر الصغيرة في الرياض على زيادة الطلبات القابلة للقياس.',
        ]);

        $this->assertFalse(app(ProjectPresenter::class)->overview($project->fresh())['description_needs_attention']);
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
        ]);

        $run->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => "تقرير {$tool->title}",
            'status' => 'published',
            'score' => $score,
            'score_band' => Report::bandFor($score),
            'summary' => 'ملخص التقرير.',
        ]);

        $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $report;
    }
}
