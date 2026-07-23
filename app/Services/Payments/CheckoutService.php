<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Billing\CreditManager;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * يربط عملية الدفع بمنح الأرصدة أو تفعيل الخطة.
 *
 * التدفّق: إنشاء Payment (pending) → checkout لدى البوابة → عودة/webhook →
 * verify → عند النجاح تُمنح الأرصدة/تُفعَّل الخطة مرة واحدة فقط (idempotent).
 */
class CheckoutService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly CreditManager $credits,
        private readonly SubscriptionManager $subscriptions,
    ) {}

    /**
     * @param  callable(Payment): array{0: string, 1: string}  $urls  يبني [return, cancel] من الدفعة
     * @return array{payment: Payment, session: CheckoutSession}
     */
    public function startCreditPackPurchase(Workspace $workspace, CreditPack $pack, callable $urls): array
    {
        $gateway = $this->requireGateway();

        $payment = Payment::create([
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->owner_id,
            'provider' => $gateway->provider,
            'purpose' => 'credit_pack',
            'credit_pack_id' => $pack->id,
            'amount' => $pack->price,
            'currency' => $pack->currency,
            'credits_granted' => $pack->credits,
            'status' => Payment::STATUS_PENDING,
        ]);

        [$return, $cancel] = $urls($payment);

        return ['payment' => $payment, 'session' => $this->openCheckout($payment, $return, $cancel)];
    }

    /**
     * @param  callable(Payment): array{0: string, 1: string}  $urls
     * @return array{payment: Payment, session: CheckoutSession}
     */
    public function startPlanPurchase(Workspace $workspace, Plan $plan, callable $urls): array
    {
        $gateway = $this->requireGateway();

        $payment = Payment::create([
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->owner_id,
            'provider' => $gateway->provider,
            'purpose' => 'plan',
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => 'SAR',
            'credits_granted' => $plan->monthly_credits,
            'status' => Payment::STATUS_PENDING,
        ]);

        [$return, $cancel] = $urls($payment);

        return ['payment' => $payment, 'session' => $this->openCheckout($payment, $return, $cancel)];
    }

    /**
     * يُستدعى عند عودة المستخدم أو من webhook: يتحقق ثم يمنح.
     */
    public function complete(Payment $payment, array $callbackData): bool
    {
        // idempotent: لا منح مزدوج إن وصل الرد أو الـwebhook مرتين.
        if ($payment->isPaid()) {
            return true;
        }

        $gateway = $this->gateways->activeGateway();

        if ($gateway === null || $gateway->provider !== $payment->provider) {
            // البوابة تغيّرت أو أُلغيت بين البدء والعودة.
            $provider = $this->gateways->provider(
                PaymentGateway::where('provider', $payment->provider)->firstOrFail()
            );
        } else {
            $provider = $this->gateways->provider($gateway);
        }

        if (! $provider->verify($payment, $callbackData)) {
            $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();

            return false;
        }

        return DB::transaction(function () use ($payment): bool {
            $payment->forceFill([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'external_id' => $payment->external_id,
            ])->save();

            $workspace = $payment->workspace;

            if ($payment->purpose === 'plan' && $payment->plan !== null) {
                $this->subscriptions->subscribe($workspace, $payment->plan);
            } elseif ($payment->credits_granted > 0) {
                $this->credits->grant($workspace, $payment->credits_granted, "شراء: {$payment->creditPack?->name}");
            }

            return true;
        });
    }

    public function cancel(Payment $payment): void
    {
        if (! $payment->isPaid()) {
            $payment->forceFill(['status' => Payment::STATUS_CANCELLED])->save();
        }
    }

    private function openCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        $provider = $this->gateways->activeProvider();
        $session = $provider->createCheckout($payment, $returnUrl, $cancelUrl);

        if ($session->externalId !== null) {
            $payment->forceFill(['external_id' => $session->externalId])->save();
        }

        return $session;
    }

    private function requireGateway(): PaymentGateway
    {
        $gateway = $this->gateways->activeGateway();

        if ($gateway === null || ! $gateway->isConfigured()) {
            throw new RuntimeException('لا توجد بوابة دفع مفعّلة. يضبطها الآدمن من اللوحة.');
        }

        return $gateway;
    }
}
