<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspaceOwner(): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $user = User::factory()->create(['locale' => 'ar']);
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Audit Account',
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
            'name' => 'Audit Workspace',
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

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Audit Project',
            'stage' => 1,
            'status' => 'active',
            'sector' => 'ecommerce',
        ]);

        return [$user, $workspace, $project];
    }

    #[Test]
    public function audit_status_endpoint_reports_an_in_progress_run(): void
    {
        [$user, $workspace, $project] = $this->makeWorkspaceOwner();

        AuditRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'status' => 'running',
            'trigger_source' => 'onboarding',
            'summary_json' => ['headline' => 'قيد التحليل'],
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson(route('projects.audit.status', $project))
            ->assertOk()
            ->assertJson(['status' => 'running', 'in_progress' => true]);
    }

    #[Test]
    public function audit_status_endpoint_reports_completed_run_as_not_in_progress(): void
    {
        [$user, $workspace, $project] = $this->makeWorkspaceOwner();

        AuditRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'trigger_source' => 'onboarding',
            'summary_json' => ['headline' => 'اكتمل'],
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson(route('projects.audit.status', $project))
            ->assertOk()
            ->assertJson(['status' => 'completed', 'in_progress' => false]);
    }
}
