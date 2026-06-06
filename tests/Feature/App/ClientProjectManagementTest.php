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

        $clientCreateResponse = $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('clients.store'), [
                'name' => 'Client Test',
                'email' => 'client@test.com',
                'phone' => '0500000000',
                'company' => 'Client Co',
                'notes' => 'Important',
                'status' => 'active',
            ]);
        $clientCreateResponse->assertStatus(302);
        $this->assertSame(route('clients.index'), $clientCreateResponse->headers->get('Location'));

        $client = Client::query()->where('workspace_id', $workspace->id)->firstOrFail();

        $projectCreateResponse = $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.store'), [
                'name' => 'Project Test',
                'client_id' => $client->id,
                'stage' => 3,
                'status' => 'active',
                'sector' => 'b2b_services',
                'verified_social_profiles_json' => [
                    [
                        'network' => 'LinkedIn',
                        'url' => 'https://www.linkedin.com/company/project-test',
                        'handle' => 'project-test',
                        'title' => 'Project Test LinkedIn',
                        'description' => 'حساب موثق يدوياً داخل المشروع.',
                        'primary_cta' => 'تواصل معنا',
                        'links_back_to_site' => '1',
                        'verification_notes' => 'verified manually',
                    ],
                ],
            ]);
        $projectCreateResponse->assertStatus(302);
        $this->assertSame(route('projects.index'), $projectCreateResponse->headers->get('Location'));

        $project = Project::query()->where('workspace_id', $workspace->id)->firstOrFail();

        $projectUpdateResponse = $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('projects.update', $project), [
                'name' => 'Project Updated',
                'client_id' => $client->id,
                'stage' => 4,
                'status' => 'paused',
                'sector' => 'b2b_services',
                'verified_social_profiles_json' => [
                    [
                        'network' => 'X',
                        'url' => 'https://x.com/projectupdated',
                        'handle' => '@projectupdated',
                        'title' => 'Project Updated X',
                        'description' => 'حساب X موثق يدوياً.',
                        'primary_cta' => 'احجز استشارة',
                        'links_back_to_site' => '1',
                        'verification_notes' => 'updated manually',
                    ],
                ],
            ]);
        $projectUpdateResponse->assertStatus(302);
        $this->assertSame(route('projects.index'), $projectUpdateResponse->headers->get('Location'));

        $accountUpdateResponse = $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
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
            ]);
        $accountUpdateResponse->assertStatus(302);
        $this->assertSame(route('account.index'), $accountUpdateResponse->headers->get('Location'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Project Updated',
            'stage' => 4,
            'status' => 'paused',
        ]);
        $this->assertSame('X', $project->fresh()->verified_social_profiles_json[0]['network']);
        $this->assertTrue((bool) $project->fresh()->verified_social_profiles_json[0]['links_back_to_site']);

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
