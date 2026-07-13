<?php

namespace Tests\Feature\Intelligence;

use App\Domain\Account\Models\Account;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Jobs\CaptureMonitoringSnapshotJob;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonitoringScheduleTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function the_monitoring_command_only_queues_snapshots_for_enabled_projects(): void
    {
        Queue::fake();

        $workspace = $this->workspace();
        $monitored = $this->project($workspace, true);
        $this->project($workspace, false); // not monitored — must be skipped

        $this->artisan('intelligence:monitoring-snapshots', ['cadence' => 'weekly'])
            ->assertSuccessful();

        Queue::assertPushed(CaptureMonitoringSnapshotJob::class, 1);
        Queue::assertPushed(
            CaptureMonitoringSnapshotJob::class,
            fn (CaptureMonitoringSnapshotJob $job): bool => $job->projectId === $monitored->id,
        );
    }

    #[Test]
    public function project_knowledge_sync_is_not_scheduled_when_disabled(): void
    {
        $this->assertFalse(config('services.knowledge.project_sync'));
        $this->assertSame([], $this->projectKnowledgeSyncEvents());
    }

    #[Test]
    public function project_knowledge_sync_runs_daily_without_overlapping_when_enabled(): void
    {
        config()->set('services.knowledge.project_sync', true);

        try {
            require base_path('routes/console.php');
            $events = $this->projectKnowledgeSyncEvents();

            $this->assertCount(1, $events);
            $this->assertSame('15 3 * * *', $events[0]->expression);
            $this->assertSame('knowledge-project-sync', $events[0]->description);
            $this->assertTrue($events[0]->withoutOverlapping);
        } finally {
            config()->set('services.knowledge.project_sync', false);
        }
    }

    #[Test]
    public function web_refresh_runs_hourly_without_overlapping_only_when_enabled(): void
    {
        config()->set('services.web_search.scheduled_refresh', true);

        try {
            require base_path('routes/console.php');
            $events = array_values(array_filter(
                Schedule::events(),
                fn ($event): bool => str_contains($event->command ?? '', 'knowledge:refresh-web'),
            ));

            $this->assertCount(1, $events);
            $this->assertSame('17 * * * *', $events[0]->expression);
            $this->assertSame('knowledge-web-refresh', $events[0]->description);
            $this->assertTrue($events[0]->withoutOverlapping);
        } finally {
            config()->set('services.web_search.scheduled_refresh', false);
        }
    }

    /** @return list<Event> */
    private function projectKnowledgeSyncEvents(): array
    {
        return array_values(array_filter(
            Schedule::events(),
            fn ($event): bool => str_contains($event->command ?? '', 'knowledge:sync-projects'),
        ));
    }

    private function workspace(): Workspace
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id, 'name' => 'Mon', 'billing_email' => $owner->email, 'status' => 'active',
        ]);

        return Workspace::query()->create([
            'account_id' => $account->id, 'name' => 'Mon WS', 'type' => 'personal', 'status' => 'active',
        ]);
    }

    private function project(Workspace $workspace, bool $monitoring): Project
    {
        return Project::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'name' => 'P',
            'stage' => 1,
            'status' => 'active',
            'sector' => 'general',
            'monitoring_enabled' => $monitoring,
        ]);
    }
}
