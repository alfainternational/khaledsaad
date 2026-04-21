<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PayPalWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'resource_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
