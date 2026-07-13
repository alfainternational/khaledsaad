<?php

namespace App\Domain\AI\Worker;

use RuntimeException;

class WorkerProtocolException extends RuntimeException
{
    public function __construct(public readonly string $protocolCode, public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
