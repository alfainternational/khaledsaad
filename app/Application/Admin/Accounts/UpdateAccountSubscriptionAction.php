<?php

namespace App\Application\Admin\Accounts;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Subscription;
use App\Models\User;

class UpdateAccountSubscriptionAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{plan_id:int,status:string,current_period_end:mixed,keep_paypal_link?:bool}  $data
     */
    public function handle(Account $account, array $data, User $actor): Subscription
    {
        $subscription = $account->subscription()->first();

        $previousPlanId = $subscription?->plan_id;
        $previousStatus = $subscription?->status;

        $keepPaypal = filter_var($data['keep_paypal_link'] ?? false, FILTER_VALIDATE_BOOL);

        $payload = [
            'plan_id' => $data['plan_id'],
            'status' => $data['status'],
            'current_period_end' => $data['current_period_end'] ?? null,
        ];

        if (! $keepPaypal) {
            $payload['paypal_subscription_id'] = null;
            $payload['checkout_plan_id'] = null;
            $payload['billing_cycle'] = null;
            $payload['cancelled_at'] = null;
        }

        $subscription = $account->subscription()->updateOrCreate(
            ['account_id' => $account->getKey()],
            $payload,
        );

        $this->auditLogger->record(
            action: 'admin.account.subscription.updated',
            targetType: 'account',
            targetId: $account->getKey(),
            actor: $actor,
            meta: [
                'account_name' => $account->name,
                'previous_plan_id' => $previousPlanId,
                'new_plan_id' => $subscription->plan_id,
                'previous_status' => $previousStatus,
                'new_status' => $subscription->status,
                'current_period_end' => optional($subscription->current_period_end)?->toDateTimeString(),
            ],
        );

        return $subscription->refresh();
    }
}
