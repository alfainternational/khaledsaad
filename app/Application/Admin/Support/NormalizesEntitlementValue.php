<?php

namespace App\Application\Admin\Support;

trait NormalizesEntitlementValue
{
    protected function normalizeValue(string $valueType, mixed $value): array
    {
        return [
            'value' => match ($valueType) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                'integer' => (int) $value,
                'float' => (float) $value,
                'json' => is_array($value) ? $value : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR),
                default => (string) $value,
            },
        ];
    }
}
