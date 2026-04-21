<?php

namespace App\Domain\AI\Models;

use App\Domain\Account\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AICreditsLedger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_credits_ledger';

    protected $fillable = [
        'account_id',
        'delta',
        'reason',
        'ref_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
