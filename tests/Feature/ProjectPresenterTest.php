<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Support\Presentation\ProjectPresenter;
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
