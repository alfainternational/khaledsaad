<?php

namespace Tests\Feature\Admin;

use App\Domain\Billing\Models\Plan;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureFlagManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_crud_feature_flags_and_the_service_respects_plan_audiences(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.feature-flags.store'), [
            'key' => 'modules.stage_2.beta',
            'name' => 'Beta Stage 2',
            'description' => 'Flag',
            'module' => 'modules.stage_2',
            'status' => 'on',
            'rollout_percentage' => 100,
            'expires_at' => null,
            'audiences' => [
                ['audience_type' => 'plan', 'audience_id' => $plan->id],
            ],
        ])->assertRedirect();

        $flag = FeatureFlag::query()->where('key', 'modules.stage_2.beta')->firstOrFail();
        $this->assertDatabaseHas('feature_flag_audiences', [
            'feature_flag_id' => $flag->id,
            'audience_type' => 'plan',
            'audience_id' => $plan->id,
        ]);

        $service = app(FeatureFlagService::class);
        $this->assertTrue($service->isEnabled('modules.stage_2.beta', [
            'plan_id' => $plan->id,
            'seed' => 'plan-'.$plan->id,
        ]));
        $this->assertFalse($service->isEnabled('modules.stage_2.beta', [
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
            'seed' => 'plan-free',
        ]));

        $this->actingAs($admin)->put(route('admin.feature-flags.update', $flag), [
            'key' => 'modules.stage_2.beta',
            'name' => 'Beta Stage 2 Updated',
            'description' => 'Flag Updated',
            'module' => 'modules.stage_2',
            'status' => 'beta',
            'rollout_percentage' => 50,
            'expires_at' => null,
            'audiences' => [
                ['audience_type' => 'plan', 'audience_id' => $plan->id],
            ],
        ])->assertSessionHas('status');

        $flag->refresh();
        $this->assertSame('Beta Stage 2 Updated', $flag->name);

        $this->actingAs($admin)->delete(route('admin.feature-flags.destroy', $flag))
            ->assertRedirect(route('admin.feature-flags.index'));

        $this->assertDatabaseMissing('feature_flags', ['id' => $flag->id]);
    }
}
