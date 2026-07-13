<?php

namespace App\Domain\AI\Knowledge\Uploads;

use RuntimeException;

class KnowledgeExtractionException extends RuntimeException
{
    public function __construct(public readonly string $machineCode, string $message)
    {
        parent::__construct($message);
    }
}
