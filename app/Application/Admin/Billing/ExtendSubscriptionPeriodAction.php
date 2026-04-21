<?php

namespace App\Application\Admin\Billing;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Subscription;
use App\Models\User;

class ExtendSubscriptionPeriodAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Subscription $subscription, int $days, User $actor): Subscription
    {
        $days = max(1, min($days, 730));

        $base = $subscription->current_period_end && $subscription->current_period_end->isFuture()
            ? $subscription->current_period_end
            : now();

        $subscription->current_period_end = $base->copy()->addDays($days);
        $subscription->save();

        $this->auditLogger->record(
            action: 'admin.subscription.period_extended',
            targetType: 'subscription',
            targetId: $subscription->getKey(),
            actor: $actor,
            workspace: null,
            meta: [
                'account_id' => $subscription->account_id,
                'days_added' => $days,
                'new_period_end' => optional($subscription->current_period_end)?->toDateTimeString(),
            ],
        );

        return $subscription->refresh();
    }
}
