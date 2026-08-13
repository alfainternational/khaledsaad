<?php

namespace App\Modules\Reporting\Validation;

final class ValidationReport
{
    /** @param array<int, ValidationViolation> $violations */
    public function __construct(public readonly array $violations) {}

    public function passes(): bool
    {
        return $this->blocking() === [];
    }

    /** @return array<int, ValidationViolation> */
    public function blocking(): array
    {
        return array_values(array_filter($this->violations, fn (ValidationViolation $item): bool => $item->severity === 'block'));
    }

    /** @return array<int, string> */
    public function codes(): array
    {
        return array_values(array_unique(array_map(fn (ValidationViolation $item): string => $item->code, $this->violations)));
    }

    /** @return array<int, array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn (ValidationViolation $item): array => $item->toArray(), $this->violations);
    }
}
