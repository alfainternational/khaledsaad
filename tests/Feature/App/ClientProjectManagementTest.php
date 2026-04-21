<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_manage_clients_projects_and_account_settings(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $user = User::factory()->create(['locale' => 'ar']);
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Pro Account',
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
            'name' => 'Pro Workspace',
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

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('clients.store'), [
                'name' => 'Client Test',
                'email' => 'client@test.com',
                'phone' => '0500000000',
                'company' => 'Client Co',
                'notes' => 'Important',
                'status' => 'active',
            ])->assertRedirect(route('clients.index'));

        $client = Client::query()->where('workspace_id', $workspace->id)->firstOrFail();

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.store'), [
                'name' => 'Project Test',
                'client_id' => $client->id,
                'stage' => 3,
                'status' => 'active',
            ])->assertRedirect(route('projects.index'));

        $project = Project::query()->where('workspace_id', $workspace->id)->firstOrFail();

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('projects.update', $project), [
                'name' => 'Project Updated',
                'client_id' => $client->id,
                'stage' => 4,
                'status' => 'paused',
            ])->assertRedirect(route('projects.index'));

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->patch(route('account.update'), [
                'name' => 'Updated User',
                'locale' => 'en',
                'account_name' => 'Updated Account',
                'billing_email' => 'billing@example.com',
                'workspace_name' => 'Updated Workspace',
                'workspace_type' => 'agency',
                'persona' => 'agency',
                'awareness_level' => 'structured',
                'primary_goal' => 'improve_marketing',
                'audience' => 'SMB owners',
                'country' => 'الإمارات',
                'content_locale' => 'ar_gulf',
                'current_challenge' => 'Weak conversion path',
            ])->assertRedirect(route('account.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Project Updated',
            'stage' => 4,
            'status' => 'paused',
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Account',
            'billing_email' => 'billing@example.com',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Updated Workspace',
            'type' => 'agency',
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'key' => 'business.profile',
        ]);
    }
}
