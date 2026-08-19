<?php

namespace Tests\Feature;

use App\Models\BillingAudit;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionAssignmentService;
use App\Services\Billing\SubscriptionManager;
use App\Support\Billing\FeatureKey;
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
        $this->assertNotNull($subscription->scheduled_change_at);
        // ترقية مجدولة ليست انسحابًا: الوسم يخصّ الهبوط للمجاني وحده.
        $this->assertFalse($subscription->cancel_at_period_end);
    }

    #[Test]
    public function a_scheduled_downgrade_to_free_is_still_marked_as_cancelling(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = User::factory()->create()->primaryWorkspace();
        $free = Plan::where('key', 'free')->firstOrFail();

        app(SubscriptionAssignmentService::class)->assign(
            [$workspace->id], $free, $admin, 'keep', 'period_end',
        );

        $this->assertTrue($workspace->subscription->fresh()->cancel_at_period_end);
    }

    #[Test]
    public function an_upgrade_grants_the_plan_credits_by_default(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = User::factory()->create()->primaryWorkspace();
        $before = $workspace->wallet->balance;
        $target = Plan::where('key', 'professional')->firstOrFail();

        app(SubscriptionAssignmentService::class)->assign([$workspace->id], $target, $admin);

        $this->assertSame($target->id, $workspace->subscription->fresh()->plan_id);
        $this->assertSame($before + $target->monthly_credits, $workspace->wallet->fresh()->balance);
    }

    #[Test]
    public function an_upgrade_grants_every_feature_the_plan_selects(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = User::factory()->create()->primaryWorkspace();
        $target = Plan::where('key', 'professional')->firstOrFail();
        $entitlements = app(Entitlements::class);

        $this->assertFalse($entitlements->allows($workspace, FeatureKey::REPORTS_PDF));

        app(SubscriptionAssignmentService::class)->assign([$workspace->id], $target, $admin);
        $entitlements->flush();

        foreach ($target->planFeatures()->where('enabled', true)->with('feature')->get() as $row) {
            $this->assertTrue(
                $entitlements->allows($workspace, $row->feature->key),
                "الترقية لم تمنح «{$row->feature->key}»",
            );
        }

        $this->assertSame(10, app(SubscriptionManager::class)->projectLimit($workspace));
    }

    #[Test]
    public function the_preview_reports_the_credit_that_will_actually_be_granted(): void
    {
        $workspace = User::factory()->create()->primaryWorkspace();
        $target = Plan::where('key', 'individual')->firstOrFail();
        $service = app(SubscriptionAssignmentService::class);

        $granting = $service->preview([$workspace->id], $target, 'plan_grant', 'now');
        $keeping = $service->preview([$workspace->id], $target, 'keep', 'now');

        $this->assertSame($target->monthly_credits, $granting['items'][0]['credit_delta']);
        $this->assertSame(0, $keeping['items'][0]['credit_delta']);
    }

    #[Test]
    public function a_due_scheduled_change_is_applied_with_its_plan_credits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = User::factory()->create()->primaryWorkspace();
        $target = Plan::where('key', 'professional')->firstOrFail();
        $before = $workspace->wallet->balance;

        app(SubscriptionAssignmentService::class)->assign(
            [$workspace->id], $target, $admin, 'plan_grant', 'period_end',
        );

        $subscription = $workspace->subscription->fresh();
        $this->assertNotSame($target->id, $subscription->plan_id);

        $subscription->forceFill(['scheduled_change_at' => now()->subMinute()])->save();
        $this->artisan('subscriptions:apply-scheduled')->assertExitCode(0);

        $applied = $workspace->subscription->fresh();
        $this->assertSame($target->id, $applied->plan_id);
        $this->assertNull($applied->scheduled_plan_id);
        $this->assertNull($applied->scheduled_change_at);
        $this->assertSame($before + $target->monthly_credits, $workspace->wallet->fresh()->balance);
        $this->assertDatabaseHas('billing_audits', [
            'workspace_id' => $workspace->id,
            'action' => 'subscription.scheduled_applied',
        ]);
    }

    #[Test]
    public function a_scheduled_change_that_is_not_due_yet_is_left_alone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = User::factory()->create()->primaryWorkspace();
        $currentPlanId = $workspace->subscription->plan_id;
        $target = Plan::where('key', 'team')->firstOrFail();

        app(SubscriptionAssignmentService::class)->assign(
            [$workspace->id], $target, $admin, 'plan_grant', 'period_end',
        );
        $workspace->subscription->fresh()
            ->forceFill(['scheduled_change_at' => now()->addWeek()])->save();

        $this->artisan('subscriptions:apply-scheduled')->assertExitCode(0);

        $this->assertSame($currentPlanId, $workspace->subscription->fresh()->plan_id);
    }
}
