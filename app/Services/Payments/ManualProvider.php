<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Contracts\Payments\GatewayHealth;
use App\Contracts\Payments\PaymentProvider;
use App\Contracts\Payments\RefundResult;
use App\Models\Payment;
use App\Models\PaymentGateway;

/**
 * تحويل بنكي/يدوي: العميل يحوّل، والآدمن يعتمد.
 *
 * كانت هذه البوابة تعتمد الدفع فورًا لمجرد العودة، أي أن أي مستخدم يحصل على
 * الرصيد بلا دفع. الآن تُنشَأ الدفعة معلّقة، ولا تُمنح إلا باعتماد آدمن من
 * لوحة المدفوعات — وهذا هو معنى «يدوي».
 */
class ManualProvider implements PaymentProvider
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function key(): string
    {
        return 'manual';
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        $payment->forceFill([
            'charged_amount' => $payment->amount,
            'charged_currency' => $payment->currency,
        ])->save();

        return new CheckoutSession(
            redirectUrl: $returnUrl,
            externalId: 'manual-'.$payment->id,
            requiresRedirect: false,
            pendingApproval: true,
            message: $this->gateway->instructions
                ?: 'سجّلنا طلبك. أرسل إشعار التحويل وسيُعتمد رصيدك فور التأكد منه.',
        );
    }

    /**
     * لا اعتماد آلي. الاعتماد يمر بـCheckoutService::approveManually بقرار آدمن.
     */
    public function verify(Payment $payment, array $callbackData): bool
    {
        return false;
    }

    public function healthCheck(): GatewayHealth
    {
        return new GatewayHealth(true, 'التحويل اليدوي جاهز ولا يحتاج اتصالًا خارجيًا.');
    }

    public function refund(Payment $payment, float $amount, string $reason): RefundResult
    {
        return new RefundResult(true, 'manual-refund-'.$payment->id, 'سُجل الاسترداد اليدوي ويجب تنفيذ التحويل للعميل خارج النظام.');
    }
}
