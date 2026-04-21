<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ping_returns_ok(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.api', 'v1');
    }

    #[Test]
    public function tokens_rejects_invalid_credentials(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->postJson('/api/v1/tokens', [
            'email' => 'nope@example.com',
            'password' => 'wrong',
            'device_name' => 'PHPUnit',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function tokens_issues_bearer_token_for_valid_user(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $user = User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->postJson('/api/v1/tokens', [
            'email' => 'api-user@example.com',
            'password' => 'secret-password',
            'device_name' => 'PHPUnit',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));
    }

    #[Test]
    public function me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    #[Test]
    public function me_returns_current_user_with_sanctum_token(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $user = User::factory()->create([
            'name' => 'API Person',
            'email' => 'me@example.com',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonPath('data.name', 'API Person')
            ->assertJsonPath('data.public_id', $user->public_id);
    }

    #[Test]
    public function workspaces_lists_active_memberships(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Acct',
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
            'name' => 'Listed WS',
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

        $token = $owner->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Listed WS')
            ->assertJsonPath('data.0.public_id', $workspace->public_id);
    }

    #[Test]
    public function workspace_projects_and_tool_endpoints_work_with_bearer_token(): void
    {
        [$owner, $workspace, $project] = $this->makeApiWorkspaceProject();

        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id;

        $this->assertNotNull(Tool::query()->where('code', 'diagnosis')->first(), 'Seeded tool diagnosis must exist');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/projects')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $project->public_id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/projects/'.$project->public_id.'/tools/diagnosis')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($base.'/projects/'.$project->public_id.'/tools/diagnosis/run', [
                'mode' => 'guided',
                'brief' => 'اختبار API',
                'inputs' => [
                    'problem' => 'ضعف الطلب',
                    'context' => 'سوق محلي',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function scoped_token_denies_other_workspace(): void
    {
        [$owner, $workspace] = $this->makeApiWorkspaceProject();

        $token = $owner->createToken('scoped', ['workspace:'.$workspace->public_id])->plainTextToken;

        $other = Workspace::query()->create([
            'account_id' => $workspace->account_id,
            'name' => 'Other',
            'type' => 'personal',
            'status' => 'active',
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $other->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/workspaces/'.$other->public_id.'/projects')
            ->assertForbidden();
    }

    #[Test]
    public function super_admin_can_list_feature_flags_via_api(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $email = config('platform.admin.email');
        $password = config('platform.admin.password');

        $tok = $this->postJson('/api/v1/tokens', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'PHPUnit',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$tok)
            ->getJson('/api/v1/admin/feature-flags')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Project}
     */
    private function makeApiWorkspaceProject(): array
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
            'name' => 'API WS',
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

        return [$owner, $workspace, $project];
    }
}
