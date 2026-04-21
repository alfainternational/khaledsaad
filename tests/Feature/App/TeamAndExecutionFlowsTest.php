<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamAndExecutionFlowsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_invite_members_run_tools_and_generate_template_outputs(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Ops Account',
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
            'name' => 'Ops Workspace',
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

        app(OnboardingState::class)->markCompleted($workspace);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Exec',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Execution Project',
            'stage' => 3,
            'status' => 'active',
        ]);

        $tool = Tool::query()->create([
            'code' => 'execution-tool',
            'name' => 'Execution Tool',
            'description' => 'Runs execution output.',
            'stage' => 3,
            'sort_order' => 1,
            'status' => 'published',
        ]);

        $template = AITemplate::query()->create([
            'code' => 'exec-template',
            'name' => 'Execution Template',
            'description' => 'Creates an execution draft.',
            'prompt_template' => 'Draft for {{project_name}} targeting {{audience}}.',
            'model' => 'gpt-5',
            'credit_cost' => 0,
            'status' => 'published',
        ]);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('team.invitations.store'), [
                'email' => 'invitee@example.com',
                'role' => 'editor',
                'expires_in_days' => 5,
            ])->assertSessionHas('status');

        $invitation = WorkspaceInvitation::query()->firstOrFail();

        $this->actingAs($invitee)
            ->post(route('team.invitations.accept', $invitation->token))
            ->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => 'editor',
        ]);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.tools.run', [$project, $tool]), [
                'mode' => 'guided',
                'brief' => 'Need execution recommendations',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tool_runs', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'tool_code' => 'execution-tool',
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.execution-tool',
        ]);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('studio.generations.store'), [
                'template_id' => $template->id,
                'project_id' => $project->id,
                'brief' => 'Need a strong positioning draft',
            ])->assertSessionHas('status');

        $this->assertDatabaseHas('ai_generations', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);

        $runId = ToolRun::query()->value('id');

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.approvals.store', $project), [
                'item_type' => 'tool_run',
                'item_id' => $runId,
                'note' => 'راجع هذا المخرج قبل التسليم',
            ])->assertSessionHas('status');

        $this->assertDatabaseHas('approvals', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => 'tool_run',
            'status' => 'pending',
        ]);
    }
}
