<?php

namespace Tests\Feature\Auth;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use App\Support\Workspaces\WorkspaceProfileStore;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_switch_between_accessible_workspaces(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Account One',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspaceA = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Workspace A',
            'type' => 'personal',
            'status' => 'active',
        ]);

        $workspaceB = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Workspace B',
            'type' => 'team',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspaceA->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspaceB->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('dashboard.workspaces.switch', $workspaceB))
            ->assertSessionHas('current_workspace_id', $workspaceB->id);
    }

    #[Test]
    public function dashboard_displays_recent_client_project_counts(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Dashboard Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Dashboard Workspace',
            'type' => 'team',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        app(OnboardingState::class)->markCompleted($workspace);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Dashboard',
            'contact_info' => ['email' => 'client@example.com'],
            'status' => 'active',
        ]);

        $projectOne = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Project One',
            'stage' => 2,
            'status' => 'active',
        ]);

        Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Project Two',
            'stage' => 4,
            'status' => 'completed',
        ]);

        // الداشبورد المبسّط يعرض «المشروع الحالي» فقط (الأحدث لمساً) لا قائمة
        // بكل المشاريع — نجعل Project One هو الأحدث بشكل حتمي.
        $projectOne->forceFill(['updated_at' => now()->addMinute()])->save();

        app(WorkspaceProfileStore::class)->put($workspace, [
            'persona' => 'team',
            'awareness_level' => 'expert',
            'primary_goal' => 'build_90_day_plan',
            'recommended_path' => 'growth_operations',
            'audience' => 'Service-based companies',
            'current_challenge' => 'Need clearer operating flow',
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('الخطوة التالية')
            ->assertSee('Client Dashboard')
            ->assertSee('Project One');
    }
}
