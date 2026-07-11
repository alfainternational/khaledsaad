<?php

namespace App\Domain\AI\Worker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntelligenceWorker extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'secret_ciphertext',
        'capabilities_json',
        'status',
        'version',
        'last_seen_at',
        'last_ip_hash',
        'meta_json',
    ];

    protected $hidden = ['secret_ciphertext'];

    protected $casts = [
        'capabilities_json' => 'array',
        'last_seen_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(IntelligenceJob::class);
    }

    public function nonces(): HasMany
    {
        return $this->hasMany(IntelligenceWorkerNonce::class);
    }
}
