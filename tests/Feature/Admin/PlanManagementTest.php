<?php

namespace Tests\Feature\Admin;

use App\Domain\Billing\Models\Plan;
use App\Domain\Entitlement\Models\Entitlement;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_update_and_delete_a_plan(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'code' => 'growth',
            'name_ar' => 'Growth',
            'name_en' => 'Growth',
            'monthly_price' => 199,
            'status' => 'active',
            'entitlements' => [
                ['key' => 'modules.stage_2', 'value_type' => 'boolean', 'value' => 'true'],
                ['key' => 'projects.max_per_workspace', 'value_type' => 'integer', 'value' => '20'],
            ],
        ])->assertRedirect();

        $plan = Plan::query()->where('code', 'growth')->firstOrFail();
        $this->assertSame(true, $plan->features_json['modules.stage_2']);
        $this->assertDatabaseHas('entitlements', [
            'scope_type' => 'plan',
            'scope_id' => $plan->id,
            'key' => 'projects.max_per_workspace',
        ]);

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'code' => 'growth',
            'name_ar' => 'Growth Plus',
            'name_en' => 'Growth Plus',
            'monthly_price' => 249,
            'status' => 'active',
            'entitlements' => [
                ['key' => 'modules.stage_2', 'value_type' => 'boolean', 'value' => 'true'],
            ],
        ])->assertSessionHas('status');

        $plan->refresh();
        $this->assertSame('Growth Plus', $plan->name_ar);
        $this->assertCount(1, Entitlement::query()->where('scope_type', 'plan')->where('scope_id', $plan->id)->get());

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}
