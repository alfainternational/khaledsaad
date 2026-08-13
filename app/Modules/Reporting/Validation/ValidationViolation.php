<?php

namespace App\Modules\Reporting\Validation;

final class ValidationViolation
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $path,
        public readonly string $message,
        public readonly string $suggestedAction,
        public readonly array $meta = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'path' => $this->path,
            'message' => $this->message,
            'suggested_action' => $this->suggestedAction,
            'meta' => $this->meta,
        ];
    }
}
