<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSubscriber extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $fillable = [
        'email',
        'status',
        'access_token_hash',
        'consented_at',
        'subscribed_at',
        'last_access_at',
    ];

    protected $hidden = [
        'access_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'last_access_at' => 'datetime',
        ];
    }
}
