<?php

namespace App\Exceptions;

use RuntimeException;

class AIProviderException extends RuntimeException
{
    public function __construct(
        string $provider,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct("تعذر إكمال الطلب عبر مزود الذكاء الاصطناعي {$provider}.");
    }
}
