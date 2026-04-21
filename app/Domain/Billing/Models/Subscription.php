<?php

namespace App\Domain\Billing\Models;

use App\Domain\Account\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'account_id',
        'plan_id',
        'checkout_plan_id',
        'status',
        'billing_cycle',
        'paypal_subscription_id',
        'current_period_end',
        'cancelled_at',
    ];

    protected $casts = [
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
