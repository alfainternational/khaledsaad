<?php

namespace Tests\Feature\Auth;

use App\Domain\Workspace\Models\Workspace;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function onboarding_creates_first_client_project_and_profile_data(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'Onboarding User',
            'email' => 'onboarding@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $workspace = Workspace::query()->firstOrFail();

        $this->post(route('onboarding.store'), [
            'account_name' => 'Growth Account',
            'workspace_name' => 'Growth Workspace',
            'workspace_type' => 'agency',
            'persona' => 'agency',
            'awareness_level' => 'expert',
            'primary_goal' => 'launch_campaigns',
            'recommended_path' => 'agency_delivery',
            'audience' => 'Founders',
            'country' => 'السعودية',
            'content_locale' => 'ar_gulf',
            'current_challenge' => 'Lack of campaign structure',
            'client_name' => 'Client Prime',
            'project_name' => 'Conversion Sprint',
            'project_stage' => 2,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('clients', [
            'workspace_id' => $workspace->id,
            'name' => 'Client Prime',
        ]);

        $this->assertDatabaseHas('projects', [
            'workspace_id' => $workspace->id,
            'name' => 'Conversion Sprint',
            'stage' => 2,
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'key' => 'system.onboarding_completed',
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'key' => 'business.profile',
        ]);

        $this->assertSame('agency', data_get($workspace->fresh()->workspaceData()->where('key', 'business.profile')->first()?->value_json, 'persona'));
    }
}
