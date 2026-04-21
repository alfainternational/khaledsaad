<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolRunApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_tool_run_request_persists_inputs_in_their_matching_fields(): void
    {
        [$owner, $workspace, $project, $tool] = $this->makeWorkspaceToolScenario();

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('api.tools.run', $tool), [
                'project_id' => $project->id,
                'mode' => 'guided',
                'brief' => 'هذه ملاحظة إضافية مرتبطة بالاقتراحات.',
                'inputs' => [
                    'offer_name' => 'باقة الانطلاقة',
                    'offer_audience' => 'المطاعم المحلية الصغيرة',
                    'offer_result' => 'طلبات أكثر خلال 30 يوماً',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.inputs.offer_name', 'باقة الانطلاقة')
            ->assertJsonPath('data.inputs.offer_audience', 'المطاعم المحلية الصغيرة')
            ->assertJsonPath('data.inputs.offer_result', 'طلبات أكثر خلال 30 يوماً')
            ->assertJsonPath('data.inputs.brief', 'هذه ملاحظة إضافية مرتبطة بالاقتراحات.');

        $run = ToolRun::query()->sole();

        $this->assertSame('guided', $run->mode);
        $this->assertSame('باقة الانطلاقة', data_get($run->inputs_json, 'offer_name'));
        $this->assertSame('المطاعم المحلية الصغيرة', data_get($run->inputs_json, 'offer_audience'));
        $this->assertSame('طلبات أكثر خلال 30 يوماً', data_get($run->inputs_json, 'offer_result'));
        $this->assertSame('هذه ملاحظة إضافية مرتبطة بالاقتراحات.', data_get($run->inputs_json, 'brief'));

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tools.offer-builder',
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.offer-builder',
        ]);
    }

    #[Test]
    public function api_tool_load_returns_adaptive_input_experience_for_the_selected_project(): void
    {
        [$owner, $workspace, $project, $tool] = $this->makeWorkspaceToolScenario();

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson(route('api.tools.load', $tool).'?project_id='.$project->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('experience.summary.project_label', 'API Project')
            ->assertJsonPath('experience.modes.guided.fields.offer_audience.priority', 'critical');

        $this->assertSame(
            'API Client',
            $response->json('experience.summary.client_label')
        );
        $this->assertNotEmpty($response->json('experience.summary.bullets'));
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Project, 3: Tool}
     */
    private function makeWorkspaceToolScenario(): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'API Account',
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
            'name' => 'API Workspace',
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
            'name' => 'API Client',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'API Project',
            'stage' => 4,
            'status' => 'active',
        ]);

        $tool = Tool::query()->firstOrCreate(
            ['code' => 'offer-builder'],
            [
                'name' => 'Offer Builder',
                'description' => 'Builds structured offers.',
                'stage' => 4,
                'sort_order' => 1,
                'status' => 'published',
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
            ],
        );

        return [$owner, $workspace, $project, $tool];
    }
}
