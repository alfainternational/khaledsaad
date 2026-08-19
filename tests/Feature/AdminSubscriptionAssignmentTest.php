<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSubscriptionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function admin_user_screen_shows_and_changes_the_users_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $target = Plan::where('key', 'individual')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('إدارة الخطة')
            ->assertSee($target->name);

        $this->actingAs($admin)->post(route('admin.users.plan.assign', $user), [
            'workspace_id' => $user->primaryWorkspace()->id,
            'plan_id' => $target->id,
            'credit_policy' => 'keep',
            'effective' => 'now',
            'confirmation' => '1',
        ])->assertRedirect(route('admin.users.edit', $user));

        $this->assertSame($target->id, $user->primaryWorkspace()->subscription->fresh()->plan_id);
    }

    #[Test]
    public function an_upgrade_that_omits_the_credit_field_still_delivers_the_plan_benefits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();
        $before = $workspace->wallet->balance;
        $target = Plan::where('key', 'professional')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.plan.assign', $user), [
            'workspace_id' => $workspace->id,
            'plan_id' => $target->id,
            'effective' => 'now',
            'confirmation' => '1',
        ])->assertRedirect(route('admin.users.edit', $user));

        $this->assertSame($target->id, $workspace->subscription->fresh()->plan_id);
        $this->assertSame($before + $target->monthly_credits, $workspace->wallet->fresh()->balance);
    }

    #[Test]
    public function admin_can_preview_and_apply_one_bulk_plan_change(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $users = User::factory()->count(2)->create();
        $workspaceIds = $users->map(fn (User $user) => $user->primaryWorkspace()->id)->all();
        $target = Plan::where('key', 'professional')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.plans.preview'), [
            'workspace_ids' => $workspaceIds,
            'plan_id' => $target->id,
            'credit_policy' => 'keep',
            'effective' => 'now',
        ])->assertOk()->assertSee('مستخدمان سيتأثران');

        $this->actingAs($admin)->post(route('admin.users.plans.assign'), [
            'workspace_ids' => $workspaceIds,
            'plan_id' => $target->id,
            'credit_policy' => 'keep',
            'effective' => 'now',
            'confirmation' => '1',
        ])->assertRedirect(route('admin.users.index'));

        foreach ($workspaceIds as $workspaceId) {
            $this->assertDatabaseHas('subscriptions', ['workspace_id' => $workspaceId, 'plan_id' => $target->id]);
        }
    }

    #[Test]
    public function admin_api_previews_and_assigns_plans_but_requires_confirmation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = User::factory()->create()->primaryWorkspace();
        $target = Plan::where('key', 'team')->firstOrFail();
        Sanctum::actingAs($admin);

        $payload = [
            'workspace_ids' => [$workspace->id],
            'plan_id' => $target->id,
            'credit_policy' => 'keep',
            'effective' => 'now',
        ];

        $this->postJson(route('api.v1.admin.users.plans.preview'), $payload)
            ->assertOk()->assertJsonPath('data.count', 1);
        $this->postJson(route('api.v1.admin.users.plans.assign'), $payload)->assertStatus(422);
        $this->postJson(route('api.v1.admin.users.plans.assign'), $payload + ['confirmation' => true])
            ->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertSame($target->id, $workspace->subscription->fresh()->plan_id);
    }
}
