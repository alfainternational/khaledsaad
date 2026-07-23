<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Contracts\Payments\PaymentProvider;
use App\Models\Payment;

/**
 * بوابة يدوية/تجريبية: تعتمد الدفع فورًا دون مزوّد خارجي.
 *
 * تُستخدم قبل ربط بوابة حقيقية، أو للتحويل البنكي حيث يؤكّد الآدمن يدويًا.
 * وجودها يجعل تدفّق الشراء كاملًا وقابلًا للاختبار دون مفاتيح خارجية.
 */
class ManualProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        // لا تحويل خارجي: العودة مباشرة إلى رابط النجاح الذي يعتمد الدفع.
        return new CheckoutSession(
            redirectUrl: $returnUrl,
            externalId: 'manual-'.$payment->id,
            requiresRedirect: false,
        );
    }

    public function verify(Payment $payment, array $callbackData): bool
    {
        // البوابة اليدوية تعتمد الدفع دائمًا عند العودة.
        return true;
    }
}
