<?php

namespace App\Services\Billing;

use App\Models\BillingAudit;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubscriptionAssignmentService
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly CreditManager $credits,
    ) {}

    /**
     * @param  array<int, int>  $workspaceIds
     * @return array{count: int, items: array<int, array<string, mixed>>}
     */
    public function preview(array $workspaceIds, Plan $plan, string $creditPolicy, string $effective): array
    {
        $items = Workspace::query()->whereKey($workspaceIds)->with(['owner', 'subscription.plan', 'wallet'])->get()
            ->map(fn (Workspace $workspace) => [
                'workspace_id' => $workspace->id,
                'workspace' => $workspace->name,
                'user' => $workspace->owner?->name,
                'current_plan' => $this->subscriptions->currentPlan($workspace)->key,
                'target_plan' => $plan->key,
                'balance' => $workspace->wallet?->balance ?? 0,
                'credit_policy' => $creditPolicy,
                'effective' => $effective,
            ])->values()->all();

        return ['count' => count($items), 'items' => $items];
    }

    /**
     * @param  array<int, int>  $workspaceIds
     * @return array{succeeded: int, failed: int, errors: array<int, array{workspace_id: int, message: string}>}
     */
    public function assign(
        array $workspaceIds,
        Plan $plan,
        User $actor,
        string $creditPolicy,
        string $effective,
        ?int $creditAmount = null,
    ): array {
        $result = ['succeeded' => 0, 'failed' => 0, 'errors' => []];

        foreach (array_values(array_unique(array_map('intval', $workspaceIds))) as $workspaceId) {
            try {
                DB::transaction(function () use ($workspaceId, $plan, $actor, $creditPolicy, $effective, $creditAmount): void {
                    $workspace = Workspace::whereKey($workspaceId)->lockForUpdate()->firstOrFail();
                    $subscription = $this->subscriptions->ensure($workspace)->fresh();
                    $before = $subscription->only([
                        'plan_id', 'status', 'current_period_starts_at', 'current_period_ends_at',
                        'cancel_at_period_end', 'scheduled_plan_id', 'scheduled_change_at', 'source',
                    ]);

                    if ($effective === 'period_end') {
                        $changeAt = $subscription->current_period_ends_at ?? now()->addMonth();
                        $subscription->forceFill([
                            'scheduled_plan_id' => $plan->id,
                            'scheduled_change_at' => $changeAt,
                            'cancel_at_period_end' => true,
                            'source' => 'admin',
                        ])->save();
                    } else {
                        $subscription = $this->subscriptions->subscribe(
                            $workspace,
                            $plan,
                            null,
                            $creditPolicy === 'plan_grant',
                            'admin',
                        );

                        if ($creditPolicy === 'add' && ($creditAmount ?? 0) > 0) {
                            $this->credits->grant($workspace, $creditAmount, 'تعديل رصيد مع تعيين خطة من الإدارة');
                        }
                    }

                    BillingAudit::create([
                        'actor_id' => $actor->id,
                        'workspace_id' => $workspace->id,
                        'action' => 'subscription.assigned',
                        'subject_type' => Subscription::class,
                        'subject_id' => $subscription->id,
                        'before' => $before,
                        'after' => $subscription->fresh()->only(array_keys($before)),
                        'metadata' => [
                            'target_plan_id' => $plan->id,
                            'credit_policy' => $creditPolicy,
                            'credit_amount' => $creditAmount,
                            'effective' => $effective,
                        ],
                    ]);
                });
                $result['succeeded']++;
            } catch (Throwable $exception) {
                report($exception);
                $result['failed']++;
                $result['errors'][] = ['workspace_id' => $workspaceId, 'message' => 'تعذر تحديث هذه المساحة.'];
            }
        }

        return $result;
    }
}
