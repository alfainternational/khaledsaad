<?php

namespace App\Contracts\Payments;

final class RefundResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $externalId = null,
        public readonly ?string $message = null,
        public readonly array $meta = [],
    ) {}
}
