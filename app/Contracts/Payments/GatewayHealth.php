<?php

namespace App\Contracts\Payments;

final class GatewayHealth
{
    public function __construct(
        public readonly bool $healthy,
        public readonly string $message,
    ) {}
}
