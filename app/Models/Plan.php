<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'key', 'name', 'interval', 'price', 'monthly_credits',
        'project_limit', 'features', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }
}
