<?php

namespace App\Contracts\Payments;

/**
 * نتيجة إنشاء عملية دفع: أين يُرسَل المستخدم لإتمامها.
 */
final class CheckoutSession
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly ?string $externalId = null,
        public readonly bool $requiresRedirect = true,
    ) {}
}
