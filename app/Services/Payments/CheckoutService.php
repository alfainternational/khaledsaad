<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentRefund;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\CreditManager;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * يربط عملية الدفع بمنح الأرصدة أو تفعيل الخطة.
 *
 * التدفّق: إنشاء Payment (pending) → checkout لدى البوابة → عودة أو إشعار →
 * verify → عند النجاح تُمنح الأرصدة/تُفعَّل الخطة مرة واحدة فقط (idempotent).
 *
 * المنح لا يحدث إلا من هنا: نقطة واحدة تعني أن كل مسارات الدفع — الويب
 * والتطبيق والإشعار والاعتماد اليدوي — تمر بنفس التحقق.
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
    public function startCreditPackPurchase(Workspace $workspace, CreditPack $pack, callable $urls, ?PaymentGateway $selectedGateway = null): array
    {
        $gateway = $this->requireGateway($selectedGateway);

        $payment = Payment::create([
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->owner_id,
            'provider' => $gateway->provider,
            'payment_gateway_id' => $gateway->id,
            'idempotency_key' => (string) Str::uuid(),
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
    public function startPlanPurchase(Workspace $workspace, Plan $plan, callable $urls, ?PaymentGateway $selectedGateway = null): array
    {
        $gateway = $this->requireGateway($selectedGateway);

        $payment = Payment::create([
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->owner_id,
            'provider' => $gateway->provider,
            'payment_gateway_id' => $gateway->id,
            'idempotency_key' => (string) Str::uuid(),
            'purpose' => 'plan',
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => config('billing.currency', 'SAR'),
            'credits_granted' => $plan->monthly_credits,
            'status' => Payment::STATUS_PENDING,
        ]);

        [$return, $cancel] = $urls($payment);

        return ['payment' => $payment, 'session' => $this->openCheckout($payment, $return, $cancel)];
    }

    /**
     * يُستدعى عند عودة المستخدم أو من الإشعار: يتحقق ثم يمنح.
     */
    public function complete(Payment $payment, array $callbackData): bool
    {
        // idempotent: لا منح مزدوج إن وصل الرد والإشعار معًا.
        if ($payment->isPaid()) {
            return true;
        }

        $gateway = $payment->gateway ?: $this->gateways->gatewayFor($payment->provider);

        if ($gateway === null) {
            // البوابة حُذفت بعد بدء العملية: لا يمكن التحقق، فلا منح.
            $payment->forceFill([
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => 'gateway_missing',
            ])->save();

            return false;
        }

        if (! $this->gateways->provider($gateway)->verify($payment, $callbackData)) {
            // الدفعة اليدوية تبقى معلّقة بانتظار الآدمن، لا تُعلَن فاشلة.
            if ($payment->provider !== 'manual') {
                $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();
            }

            return false;
        }

        return $this->fulfil($payment);
    }

    /**
     * اعتماد تحويل يدوي بقرار آدمن. هذا هو البديل الوحيد عن تحقق البوابة.
     */
    public function approveManually(Payment $payment, User $admin): bool
    {
        if ($payment->isPaid()) {
            return true;
        }

        $payment->forceFill([
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'failure_reason' => null,
        ])->save();

        return $this->fulfil($payment);
    }

    public function reject(Payment $payment, ?string $reason = null): void
    {
        if ($payment->isPaid()) {
            return;
        }

        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => $reason ?? 'rejected_by_admin',
        ])->save();
    }

    public function cancel(Payment $payment): void
    {
        if (! $payment->isPaid()) {
            $payment->forceFill(['status' => Payment::STATUS_CANCELLED])->save();
        }
    }

    public function refund(Payment $payment, float $amount, User $actor, string $reason): PaymentRefund
    {
        if (! $payment->isPaid()) {
            throw new RuntimeException('لا يمكن استرداد دفعة غير مكتملة.');
        }

        $maximum = (float) ($payment->charged_amount ?? $payment->amount);
        $remaining = round($maximum - (float) $payment->refunded_amount, 2);

        if ($amount <= 0 || $amount > $remaining) {
            throw new RuntimeException('قيمة الاسترداد تتجاوز المبلغ المتبقي القابل للاسترداد.');
        }

        $gateway = $payment->gateway ?: $this->gateways->gatewayFor($payment->provider);
        if ($gateway === null) {
            throw new RuntimeException('بوابة الدفعة الأصلية غير موجودة.');
        }

        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'requested_by' => $actor->id,
            'provider' => $payment->provider,
            'amount' => $amount,
            'currency' => $payment->charged_currency ?: $payment->currency,
            'status' => 'pending',
            'reason' => $reason,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $result = $this->gateways->provider($gateway)->refund($payment, $amount, $reason);
        $refund->update([
            'external_id' => $result->externalId,
            'status' => $result->successful ? 'completed' : 'failed',
            'meta' => [...$result->meta, 'message' => $result->message],
            'processed_at' => now(),
        ]);

        if (! $result->successful) {
            throw new RuntimeException($result->message ?? 'رفضت بوابة الدفع طلب الاسترداد.');
        }

        DB::transaction(function () use ($payment, $amount): void {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $fresh->update(['refunded_amount' => round((float) $fresh->refunded_amount + $amount, 2)]);
        });

        return $refund->fresh();
    }

    /**
     * منح ما دُفع مقابله. محاط بمعاملة وبقفل الصف حتى لا يمنح رد المتصفح
     * والإشعار المرتين لو وصلا في اللحظة نفسها.
     */
    private function fulfil(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment): bool {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->isPaid()) {
                return true;
            }

            $fresh->forceFill([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
            ])->save();

            $workspace = $fresh->workspace;

            if ($fresh->purpose === 'plan' && $fresh->plan !== null) {
                // الاشتراك يمنح رصيد الخطة داخله، فلا نمنح مرتين.
                $this->subscriptions->subscribe($workspace, $fresh->plan, $fresh);
            } elseif ($fresh->credits_granted > 0) {
                // مفتاحه الدفعة نفسها: إشعار البوابة يصل مرتين بحكم
                // تصميمه، ومنحُه مرتين يعني رصيدًا مجانيًّا صامتًا.
                $this->credits->grant(
                    $workspace,
                    $fresh->credits_granted,
                    "شراء: {$fresh->creditPack?->name}",
                    "grant:payment:{$fresh->id}",
                );
            }

            $payment->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    private function openCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        $gateway = $payment->gateway ?: $this->gateways->gatewayFor($payment->provider);
        $provider = $this->gateways->provider($this->requireGateway($gateway));
        $session = $provider->createCheckout($payment, $returnUrl, $cancelUrl);

        if ($session->externalId !== null) {
            $payment->forceFill(['external_id' => $session->externalId])->save();
        }

        return $session;
    }

    private function requireGateway(?PaymentGateway $selected = null): PaymentGateway
    {
        $gateway = $selected ?? $this->gateways->activeGateway();

        if ($gateway === null || ! $gateway->isConfigured()) {
            throw new RuntimeException('لا توجد بوابة دفع مفعّلة ومكتملة المفاتيح. يضبطها الآدمن من اللوحة.');
        }

        return $gateway;
    }
}
