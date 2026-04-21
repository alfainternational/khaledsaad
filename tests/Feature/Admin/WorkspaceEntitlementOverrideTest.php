<?php

namespace Tests\Feature\Admin;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceEntitlementOverrideTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workspace_override_takes_precedence_over_plan_default_and_is_reversible(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Starter Account',
            'billing_email' => 'billing@example.com',
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Workspace 1',
            'type' => 'personal',
            'status' => 'active',
        ]);

        $resolver = app(EntitlementResolver::class);
        $this->assertSame(false, $resolver->value('modules.ai_studio', $workspace));

        $this->actingAs($admin)->post(route('admin.workspaces.entitlements.store', $workspace), [
            'key' => 'modules.ai_studio',
            'value_type' => 'boolean',
            'value' => 'true',
        ])->assertSessionHas('status');

        $this->assertSame(true, $resolver->value('modules.ai_studio', $workspace));

        $override = Entitlement::query()
            ->where('scope_type', 'workspace')
            ->where('scope_id', $workspace->id)
            ->where('key', 'modules.ai_studio')
            ->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.workspaces.entitlements.destroy', [$workspace, $override]))
            ->assertSessionHas('status');

        $this->assertSame(false, $resolver->value('modules.ai_studio', $workspace));
    }
}
