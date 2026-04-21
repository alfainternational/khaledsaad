<?php

namespace App\Domain\FeatureFlag\Models;

use App\Enums\FeatureFlagStatus;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureFlag extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'key',
        'name',
        'description',
        'module',
        'status',
        'rollout_percentage',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'status' => FeatureFlagStatus::class,
    ];

    public function audiences(): HasMany
    {
        return $this->hasMany(FeatureFlagAudience::class);
    }
}
