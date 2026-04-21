<?php

namespace App\Domain\FeatureFlag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlagAudience extends Model
{
    protected $fillable = [
        'feature_flag_id',
        'audience_type',
        'audience_id',
    ];

    public function featureFlag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class);
    }
}
