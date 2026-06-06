<?php

namespace Tests\Feature\Intelligence;

use App\Domain\Account\Models\Account;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Jobs\CaptureMonitoringSnapshotJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonitoringScheduleTest extends TestCase
{
    use RefreshDatabase;

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
