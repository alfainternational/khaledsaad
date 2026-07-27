<?php

namespace App\Contracts\Payments;

use App\Models\Payment;

/**
 * عقد موحّد لكل بوابة دفع.
 *
 * إضافة بوابة جديدة (Moyasar, Tap, ...) = صنف يحقق هذا العقد ويُسجَّل في
 * PaymentGatewayManager. لا تغيير في بقية النظام.
 */
interface PaymentProvider
{
    /**
     * ينشئ عملية شراء لدى البوابة ويعيد رابط التحويل لإتمام الدفع.
     */
    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession;

    /**
     * يتحقق من نجاح الدفع بعد عودة المستخدم أو عبر webhook.
     * يعيد true إذا كان مدفوعًا فعليًا لدى البوابة.
     */
    public function verify(Payment $payment, array $callbackData): bool;

    public function healthCheck(): GatewayHealth;

    public function refund(Payment $payment, float $amount, string $reason): RefundResult;

    /**
     * اسم المزوّد كما في جدول payment_gateways.
     */
    public function key(): string;
}
