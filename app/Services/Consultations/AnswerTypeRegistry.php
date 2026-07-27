<?php

namespace App\Services\Consultations;

final class AnswerTypeRegistry
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'text', 'textarea', 'select', 'radio', 'multiselect', 'boolean', 'confirmation',
            'number', 'range', 'scale', 'url', 'email', 'date', 'ranking', 'repeater',
        ];
    }
}
