<?php

namespace Tests\Feature;

use App\Models\BillingAudit;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionAssignmentService;
use App\Services\Billing\SubscriptionManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function selecting_the_current_plan_again_does_not_grant_credits_again(): void
    {
        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();
        $plan = $workspace->subscription->plan;
        $before = $workspace->wallet->balance;

        app(SubscriptionManager::class)->subscribe($workspace, $plan);

        $this->assertSame($before, $workspace->wallet->fresh()->balance);
        $this->assertSame($plan->id, $workspace->subscription->fresh()->plan_id);
    }

    #[Test]
    public function admin_can_preview_and_assign_a_plan_without_implicitly_changing_credit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();
        $target = Plan::where('key', 'professional')->firstOrFail();
        $beforeBalance = $workspace->wallet->balance;
        $service = app(SubscriptionAssignmentService::class);

        $preview = $service->preview([$workspace->id], $target, 'keep', 'now');

        $this->assertSame(1, $preview['count']);
        $this->assertSame('free', $preview['items'][0]['current_plan']);
        $this->assertSame('professional', $preview['items'][0]['target_plan']);

        $result = $service->assign([$workspace->id], $target, $admin, 'keep', 'now');

        $this->assertSame(1, $result['succeeded']);
        $this->assertSame($target->id, $workspace->subscription->fresh()->plan_id);
        $this->assertSame($beforeBalance, $workspace->wallet->fresh()->balance);
        $this->assertSame(1, BillingAudit::where('action', 'subscription.assigned')->count());
    }

    #[Test]
    public function a_scheduled_assignment_keeps_the_current_plan_until_the_selected_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = User::factory()->create()->primaryWorkspace();
        $currentPlanId = $workspace->subscription->plan_id;
        $target = Plan::where('key', 'individual')->firstOrFail();

        app(SubscriptionAssignmentService::class)->assign(
            [$workspace->id],
            $target,
            $admin,
            'keep',
            'period_end',
        );

        $subscription = $workspace->subscription->fresh();
        $this->assertSame($currentPlanId, $subscription->plan_id);
        $this->assertSame($target->id, $subscription->scheduled_plan_id);
        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertNotNull($subscription->scheduled_change_at);
    }
}
