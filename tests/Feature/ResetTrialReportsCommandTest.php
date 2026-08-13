<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResetTrialReportsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dry_run_is_the_default_and_preserves_everything(): void
    {
        [$user, $project, $run, $report] = $this->fixture();
        $report->sections()->create([
            'key' => 'score', 'title' => 'الدرجة', 'content_json' => ['score' => 50], 'sort_order' => 0,
        ]);
        $report->feedback()->create([
            'user_id' => $user->id, 'verdict' => 'useful', 'note' => 'ملاحظة تجريبية',
        ]);
        Storage::put('reports/trial.pdf', 'PDF fixture');
        $report->forceFill(['pdf_path' => 'reports/trial.pdf'])->save();

        $this->artisan('reports:trial-reset')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('content_feedback', [
            'subject_type' => Report::class, 'subject_id' => $report->id,
        ]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('tool_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('reports', ['id' => $report->id]);
    }

    #[Test]
    public function execute_requires_a_backup_directory(): void
    {
        $this->artisan('reports:trial-reset', ['--execute' => true])
            ->assertFailed();
    }

    #[Test]
    public function execute_backs_up_and_removes_only_report_domain_records(): void
    {
        Storage::fake('local');
        [$user, $project, $run, $report] = $this->fixture();
        $report->sections()->create([
            'key' => 'score', 'title' => 'الدرجة', 'content_json' => ['score' => 50], 'sort_order' => 0,
        ]);
        $report->feedback()->create([
            'user_id' => $user->id, 'verdict' => 'useful', 'note' => 'ملاحظة تجريبية',
        ]);
        Storage::put('reports/trial.pdf', 'PDF fixture');
        $report->forceFill(['pdf_path' => 'reports/trial.pdf'])->save();
        $backup = storage_path('framework/testing/trial-report-backup');
        if (! is_dir($backup)) {
            mkdir($backup, 0777, true);
        }

        $this->artisan('reports:trial-reset', ['--execute' => true, '--backup' => $backup])
            ->assertSuccessful();

        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
        $this->assertDatabaseMissing('content_feedback', [
            'subject_type' => Report::class, 'subject_id' => $report->id,
        ]);
        $this->assertDatabaseHas('tool_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $files = glob($backup.DIRECTORY_SEPARATOR.'trial-reports-*.json');
        $this->assertNotEmpty($files);
        $payload = json_decode(file_get_contents(collect($files)->sortDesc()->first()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('score', data_get($payload, 'reports.0.sections.0.key'));
        $this->assertSame('useful', data_get($payload, 'reports.0.feedback.0.verdict'));
        $this->assertSame('copied', data_get($payload, 'pdf_files.0.status'));
        $this->assertFileExists($backup.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, data_get($payload, 'pdf_files.0.backup')));
        Storage::assertMissing('reports/trial.pdf');
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['workspace_id' => $user->primaryWorkspace()->id, 'name' => 'تجريبي', 'slug' => 'trial-'.uniqid()]);
        $tool = Tool::create(['key' => 'trial-'.uniqid(), 'name' => 'تجريبي', 'title' => 'تجريبي', 'description' => 'وصف', 'category' => 'diagnosis', 'status' => 'published']);
        $version = ToolVersion::create(['tool_id' => $tool->id, 'version' => 1, 'credit_cost' => 0, 'status' => 'published', 'output_schema' => [], 'scoring_rules' => [], 'section_plan' => []]);
        $run = ToolRun::create(['project_id' => $project->id, 'tool_version_id' => $version->id, 'user_id' => $user->id, 'status' => ToolRun::STATUS_COMPLETED]);
        $report = Report::create(['tool_run_id' => $run->id, 'project_id' => $project->id, 'title' => 'تقرير تجريبي', 'status' => 'published', 'score' => 50]);

        return [$user, $project, $run, $report];
    }
}
