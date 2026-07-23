<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestSession extends Model
{
    protected $fillable = ['token_hash', 'claimed_by', 'claimed_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ToolRun::class);
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function isClaimable(): bool
    {
        return $this->claimed_by === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
