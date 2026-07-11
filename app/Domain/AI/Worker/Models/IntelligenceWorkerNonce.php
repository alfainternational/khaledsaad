<?php

namespace App\Domain\AI\Worker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceWorkerNonce extends Model
{
    protected $fillable = [
        'intelligence_worker_id',
        'nonce',
        'request_timestamp',
        'expires_at',
    ];

    protected $casts = [
        'intelligence_worker_id' => 'integer',
        'request_timestamp' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(IntelligenceWorker::class, 'intelligence_worker_id');
    }
}
