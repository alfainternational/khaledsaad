<?php

namespace App\Application\Billing;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;

class FinalizePayPalSubscriptionAction
{
    public function handle(Subscription $subscription): void
    {
        if ($subscription->checkout_plan_id === null) {
            return;
        }

        $subscription->forceFill([
            'plan_id' => $subscription->checkout_plan_id,
            'checkout_plan_id' => null,
            'status' => 'active',
            'current_period_end' => $subscription->billing_cycle === 'annual'
                ? now()->addYear()
                : now()->addMonth(),
        ])->save();
    }

    public function downgradeToFree(Subscription $subscription): void
    {
        $free = Plan::query()->where('code', 'free')->first();
        if ($free === null) {
            return;
        }

        $subscription->forceFill([
            'plan_id' => $free->id,
            'checkout_plan_id' => null,
            'paypal_subscription_id' => null,
            'billing_cycle' => null,
            'status' => 'active',
            'cancelled_at' => now(),
            'current_period_end' => null,
        ])->save();
    }
}
