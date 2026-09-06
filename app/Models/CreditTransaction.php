<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    public const TYPE_HOLD = 'hold';

    public const TYPE_CHARGE = 'charge';

    public const TYPE_REFUND = 'refund';

    public const TYPE_GRANT = 'grant';

    protected $fillable = [
        'credit_wallet_id', 'tool_run_id', 'type', 'amount', 'balance_after', 'reason',
        'idempotency_key',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CreditWallet::class, 'credit_wallet_id');
    }

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_HOLD => 'حجز',
            self::TYPE_CHARGE => 'خصم',
            self::TYPE_REFUND => 'استرداد',
            self::TYPE_GRANT => 'إضافة',
            default => $this->type,
        };
    }
}
