<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * يُرمى عندما يعيد المزود مخرجًا لا يطابق الشكل المتوقع.
 * القاعدة: مخرج غير صالح لا يصل إلى المستخدم إطلاقًا — يُعاد التشغيل بدلًا من عرضه.
 */
class AIInvalidOutputException extends RuntimeException
{
    /**
     * @param  array<int, string>  $violations
     */
    public function __construct(
        string $message,
        public readonly array $violations = [],
    ) {
        parent::__construct($message);
    }
}
