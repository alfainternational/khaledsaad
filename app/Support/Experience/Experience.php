<?php

namespace App\Support\Experience;

enum Experience: string
{
    case BUSINESS = 'business';
    case LEARNING = 'learning';

    public function enabledAtColumn(): string
    {
        return match ($this) {
            self::BUSINESS => 'business_experience_enabled_at',
            self::LEARNING => 'learning_experience_enabled_at',
        };
    }
}
