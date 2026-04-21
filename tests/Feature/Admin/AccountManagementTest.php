<?php

namespace Tests\Feature\Admin;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_update_and_delete_accounts(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $owner = User::factory()->create();
        $replacementOwner = User::factory()->create();
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'owner_user_id' => $owner->id,
            'name' => 'Agency Operations',
            'billing_email' => 'agency@example.com',
            'status' => 'active',
            'plan_id' => $plan->id,
            'subscription_status' => 'trialing',
            'current_period_end' => now()->addDays(14)->toDateTimeString(),
        ])->assertRedirect();

        $account = Account::query()->where('name', 'Agency Operations')->firstOrFail();

        $this->assertDatabaseHas('subscriptions', [
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
        ]);

        $this->assertDatabaseHas('account_members', [
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $this->actingAs($admin)->put(route('admin.accounts.update', $account), [
            'owner_user_id' => $replacementOwner->id,
            'name' => 'Agency Operations Plus',
            'billing_email' => 'agency-plus@example.com',
            'status' => 'suspended',
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'current_period_end' => now()->addDays(30)->toDateTimeString(),
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'owner_user_id' => $replacementOwner->id,
            'name' => 'Agency Operations Plus',
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('account_members', [
            'account_id' => $account->id,
            'user_id' => $replacementOwner->id,
            'role' => 'owner',
        ]);

        $this->actingAs($admin)->delete(route('admin.accounts.destroy', $account))
            ->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.account.deleted',
            'target_type' => 'account',
            'target_id' => $account->id,
        ]);
    }

    #[Test]
    public function admin_can_update_account_status_and_subscription_from_account_page(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $owner = User::factory()->create();
        $freePlan = Plan::query()->where('code', 'free')->firstOrFail();
        $starterPlan = Plan::query()->where('code', 'starter')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Acme Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
        ]);

        Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Acme Workspace',
            'type' => 'team',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.accounts.status', $account), [
                'status' => 'suspended',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'suspended',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.accounts.subscription', $account), [
                'plan_id' => $starterPlan->id,
                'status' => 'past_due',
                'current_period_end' => now()->addDays(10)->toDateTimeString(),
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('subscriptions', [
            'account_id' => $account->id,
            'plan_id' => $starterPlan->id,
            'status' => 'past_due',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.account.status.updated',
            'target_type' => 'account',
            'target_id' => $account->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.account.subscription.updated',
            'target_type' => 'account',
            'target_id' => $account->id,
        ]);
    }
}
