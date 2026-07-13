<?php

namespace App\Domain\AI\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntelligenceEvaluationRun extends Model
{
    protected $fillable = ['public_id', 'engine', 'model_name', 'model_version', 'case_count', 'recall_at_k', 'mean_reciprocal_rank', 'latency_ms', 'status', 'meta_json', 'completed_at'];

    protected $casts = ['case_count' => 'integer', 'recall_at_k' => 'float', 'mean_reciprocal_rank' => 'float', 'latency_ms' => 'integer', 'meta_json' => 'array', 'completed_at' => 'datetime'];

    public function results(): HasMany
    {
        return $this->hasMany(IntelligenceEvaluationResult::class, 'intelligence_evaluation_run_id');
    }
}
