<?php

namespace App\Application\Billing;

use App\Domain\Billing\Models\Subscription;
use Illuminate\Support\Facades\Log;

class ProcessPayPalWebhookAction
{
    public function __construct(
        private readonly FinalizePayPalSubscriptionAction $finalize,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(string $eventType, array $event): void
    {
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];

        match ($eventType) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($resource),
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.EXPIRED' => $this->handleSubscriptionEnded($resource),
            default => Log::info('PayPal Webhook: نوع غير معالج', ['type' => $eventType]),
        };
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleSubscriptionActivated(array $resource): void
    {
        $subscriptionId = isset($resource['id']) ? (string) $resource['id'] : null;
        if ($subscriptionId === null || $subscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('paypal_subscription_id', $subscriptionId)
            ->first();

        if ($subscription === null) {
            Log::warning('PayPal: اشتراك محلي غير موجود', ['paypal_id' => $subscriptionId]);

            return;
        }

        $this->finalize->handle($subscription);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function handleSubscriptionEnded(array $resource): void
    {
        $subscriptionId = isset($resource['id']) ? (string) $resource['id'] : null;
        if ($subscriptionId === null || $subscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('paypal_subscription_id', $subscriptionId)
            ->first();

        if ($subscription === null) {
            return;
        }

        $this->finalize->downgradeToFree($subscription);
    }
}
