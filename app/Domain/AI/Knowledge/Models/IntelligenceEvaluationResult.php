<?php

namespace App\Domain\AI\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceEvaluationResult extends Model
{
    protected $fillable = ['intelligence_evaluation_run_id', 'intelligence_evaluation_case_id', 'rank', 'reciprocal_rank', 'latency_ms', 'passed', 'diagnostics_json'];

    protected $casts = ['rank' => 'integer', 'reciprocal_rank' => 'float', 'latency_ms' => 'integer', 'passed' => 'boolean', 'diagnostics_json' => 'array'];
}
