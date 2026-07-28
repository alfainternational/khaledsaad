<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Alerts\RunNotifier;
use App\Notifications\ReportReadyNotification;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function the_owner_is_notified_when_a_report_is_ready(): void
    {
        Notification::fake();

        [$owner, $run] = $this->completedRun();

        app(RunNotifier::class)->reportReady($run);

        Notification::assertSentTo($owner, ReportReadyNotification::class);
    }

    #[Test]
    public function the_bell_shows_the_unread_count_and_marking_read_clears_it(): void
    {
        [$owner, $run] = $this->completedRun();
        app(RunNotifier::class)->reportReady($run);

        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());

        $this->actingAs($owner)
            ->get(route('app.notifications.index'))
            ->assertOk()
            ->assertSee('تقريرك جاهز');

        $this->actingAs($owner)->post(route('app.notifications.read-all'));

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function the_notifications_endpoint_returns_json_for_the_bell(): void
    {
        [$owner, $run] = $this->completedRun();
        app(RunNotifier::class)->reportReady($run);

        $this->actingAs($owner)
            ->getJson(route('app.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.title', 'تقريرك جاهز');
    }

    /**
     * @return array{0: User, 1: ToolRun}
     */
    private function completedRun(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الإشعار']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 50])->save();

        Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير الإشعار',
            'status' => 'published',
            'score' => 50,
            'score_band' => 'يحتاج ترتيبًا',
            'summary' => 'ملخص.',
        ]);

        return [$user, $run->refresh()];
    }
}
