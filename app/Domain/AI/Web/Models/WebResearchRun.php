<?php

namespace App\Domain\AI\Web\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebResearchRun extends Model
{
    protected $fillable = [
        'public_id', 'query', 'query_hash', 'status', 'requested_depth',
        'result_count', 'verified_count', 'conflict_count', 'started_at',
        'completed_at', 'checkpoint_json', 'error_code',
    ];

    protected $casts = [
        'requested_depth' => 'integer',
        'result_count' => 'integer',
        'verified_count' => 'integer',
        'conflict_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'checkpoint_json' => 'array',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(WebResearchResult::class);
    }
}
