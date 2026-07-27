<?php

namespace App\Contracts\Payments;

/**
 * نتيجة إنشاء عملية دفع: أين يُرسَل المستخدم لإتمامها.
 *
 * pendingApproval يعني: لا تحويل ولا اعتماد آلي — الدفعة تنتظر تأكيد آدمن
 * (التحويل البنكي). بدونها كانت البوابة اليدوية تمنح الرصيد لمجرد ضغط الزر.
 */
final class CheckoutSession
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly ?string $externalId = null,
        public readonly bool $requiresRedirect = true,
        public readonly bool $pendingApproval = false,
        public readonly ?string $message = null,
    ) {}
}
