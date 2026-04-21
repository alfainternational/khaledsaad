<?php

namespace Tests\Feature\Auth;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_register_and_get_personal_account_workspace_and_subscription(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'Khaled Saad',
            'email' => 'khaled@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'account_name' => 'Khaled Studio',
            'workspace_name' => 'My First Workspace',
        ])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'khaled@example.com']);
        $this->assertDatabaseHas('accounts', ['name' => 'Khaled Studio']);
        $this->assertDatabaseHas('workspaces', ['name' => 'My First Workspace']);

        $account = Account::query()->where('billing_email', 'khaled@example.com')->firstOrFail();
        $workspace = Workspace::query()->where('account_id', $account->id)->firstOrFail();
        $subscription = Subscription::query()->where('account_id', $account->id)->firstOrFail();

        $this->assertSame('free', $subscription->plan->code);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
    }

    #[Test]
    public function active_user_can_log_in_and_reach_onboarding_then_dashboard(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        auth()->logout();

        $this->post(route('login.store'), [
            'email' => 'user1@example.com',
            'password' => 'Password123!',
        ])->assertRedirect(route('onboarding.show'));

        $this->actingAs(User::query()->where('email', 'user1@example.com')->firstOrFail())
            ->post(route('onboarding.store'), [
                'account_name' => 'User One',
                'workspace_name' => 'User Workspace',
                'workspace_type' => 'personal',
                'persona' => 'idea',
                'awareness_level' => 'structured',
                'primary_goal' => 'clarify_idea',
                'audience' => 'SMBs',
                'country' => 'مصر',
                'content_locale' => 'ar_egypt',
                'current_challenge' => 'Clarify the first offer',
                'client_name' => 'Client One',
                'project_name' => 'Project One',
                'project_stage' => 1,
            ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('الخطوة التالية');
    }
}
