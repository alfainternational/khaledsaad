<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_id', 'requested_by', 'provider', 'external_id', 'amount',
        'currency', 'status', 'reason', 'idempotency_key', 'meta', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'float', 'meta' => 'array', 'processed_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
