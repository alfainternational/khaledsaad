<?php

namespace App\Http\Api;

use RuntimeException;
use Throwable;

/**
 * استثناء API موحّد يحمل رمزاً دلالياً (code) ورمز حالة HTTP.
 * يُرجعه المعالج المركزي بالشكل: { message, code, errors? }.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function entitlementRequired(string $message = 'هذه الميزة غير متاحة في باقتك الحالية.'): self
    {
        return new self($message, 'ENTITLEMENT_REQUIRED', 403);
    }

    public static function creditsExhausted(string $message = 'انتهى رصيد الذكاء الاصطناعي في باقتك.'): self
    {
        return new self($message, 'AI_CREDITS_EXHAUSTED', 402);
    }

    public static function forbidden(string $message = 'ليست لديك صلاحية لهذا الإجراء.'): self
    {
        return new self($message, 'FORBIDDEN', 403);
    }

    public static function notFound(string $message = 'العنصر المطلوب غير موجود.'): self
    {
        return new self($message, 'NOT_FOUND', 404);
    }
}
