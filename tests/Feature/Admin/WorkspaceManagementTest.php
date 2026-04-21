<?php

namespace Tests\Feature\Admin;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_update_and_delete_workspaces(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $owner = User::factory()->create();
        $workspaceOwner = User::factory()->create();
        $plan = Plan::query()->where('code', 'team')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Team Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('admin.workspaces.store'), [
            'account_id' => $account->id,
            'owner_user_id' => $workspaceOwner->id,
            'name' => 'Execution Room',
            'type' => 'team',
            'status' => 'active',
        ])->assertRedirect();

        $workspace = Workspace::query()->where('name', 'Execution Room')->firstOrFail();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $workspaceOwner->id,
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('account_members', [
            'account_id' => $account->id,
            'user_id' => $workspaceOwner->id,
            'role' => 'admin',
        ]);

        $this->actingAs($admin)->put(route('admin.workspaces.update', $workspace), [
            'account_id' => $account->id,
            'owner_user_id' => $owner->id,
            'name' => 'Execution Room Updated',
            'type' => 'agency',
            'status' => 'paused',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Execution Room Updated',
            'type' => 'agency',
            'status' => 'paused',
        ]);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $this->actingAs($admin)->delete(route('admin.workspaces.destroy', $workspace))
            ->assertRedirect(route('admin.workspaces.index'));

        $this->assertDatabaseMissing('workspaces', [
            'id' => $workspace->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.workspace.deleted',
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
        ]);
    }

    #[Test]
    public function admin_can_view_workspace_details_and_update_its_status(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Workspace Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Strategy Hub',
            'type' => 'agency',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Alpha',
            'status' => 'active',
        ]);

        Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Launch Project',
            'stage' => 2,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workspaces.show', $workspace))
            ->assertOk()
            ->assertSee('Strategy Hub')
            ->assertSee('Client Alpha')
            ->assertSee('Launch Project');

        $this->actingAs($admin)
            ->patch(route('admin.workspaces.status', $workspace), [
                'status' => 'paused',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'status' => 'paused',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.workspace.status.updated',
            'target_type' => 'workspace',
            'target_id' => $workspace->id,
        ]);
    }
}
