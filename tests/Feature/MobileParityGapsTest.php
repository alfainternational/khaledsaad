<?php

namespace Tests\Feature;

use App\Models\ContentFeedback;
use App\Models\ProjectCompetitor;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الفجوتان اللتان كانتا تكسران تطابق التطبيق مع الويب: المنافسون ورفع الملفات.
 * الآن للتطبيق نظير API لكل منهما عبر نفس الخدمات.
 */
class MobileParityGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function the_app_can_add_confirm_and_dismiss_competitors(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المنافسين']);

        Sanctum::actingAs($user);

        // إضافة منافس محلي.
        $this->postJson(route('api.v1.competitors.store', $project->slug), ['names' => 'متجر الحي'])
            ->assertCreated()
            ->assertJsonPath('data.has_local', true);

        $competitor = $project->competitors()->firstOrFail();
        $this->assertSame(ProjectCompetitor::STATUS_CONFIRMED, $competitor->status);

        // مرشّح يمكن استبعاده.
        $candidate = $project->competitors()->create([
            'name' => 'منافس إقليمي', 'source' => 'suggested',
            'tier' => 'regional', 'status' => 'candidate',
        ]);

        $this->postJson(route('api.v1.competitors.confirm', $candidate))->assertOk();
        $this->assertSame('confirmed', $candidate->fresh()->status);

        $this->postJson(route('api.v1.competitors.dismiss', $candidate))->assertOk();
        $this->assertSame('dismissed', $candidate->fresh()->status);
    }

    #[Test]
    public function the_app_can_upload_and_delete_a_run_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الرفع']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.runs.files.store', $run->uuid), [
            'file' => UploadedFile::fake()->createWithContent('evidence.txt', 'ميزانيتنا 8000'),
        ])->assertCreated()->assertJsonCount(1, 'data');

        $file = $run->files()->firstOrFail();

        $this->deleteJson(route('api.v1.runs.files.destroy', [$run->uuid, $file->id]))
            ->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(0, $run->fresh()->files()->count());
    }

    #[Test]
    public function competitor_and_file_endpoints_reject_other_users(): void
    {
        $owner = User::factory()->create();
        $project = app(ProjectService::class)->create($owner, ['name' => 'خاص']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.competitors.index', $project->slug))->assertNotFound();
        $this->postJson(route('api.v1.competitors.store', $project->slug), ['names' => 'x'])->assertNotFound();
    }

    #[Test]
    public function report_api_exposes_the_same_review_and_growth_context_as_the_web(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع تقرير التطبيق']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 64])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير مراجع',
            'status' => 'published',
            'score' => 64,
            'score_band' => Report::bandFor(64),
            'summary' => 'ملخص مراجع.',
            'review_mode' => 'manual',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        ReportWatcher::create([
            'report_id' => $report->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ReportWatcher::STATUS_ACTIVE,
            'baseline_fingerprint' => 'baseline',
            'changes' => [],
        ]);

        ContentFeedback::create([
            'user_id' => $user->id,
            'subject_type' => Report::class,
            'subject_id' => $report->id,
            'verdict' => ContentFeedback::VERDICT_UP,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.reports.show', $report))
            ->assertOk()
            ->assertJsonPath('data.is_manually_reviewed', true)
            ->assertJsonPath('watcher.status', ReportWatcher::STATUS_ACTIVE)
            ->assertJsonPath('my_verdict', ContentFeedback::VERDICT_UP)
            ->assertJsonPath('suggestion.tool.key', 'brand-clarity');
    }
}
