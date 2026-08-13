<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recommendation extends Model
{
    protected $fillable = [
        'finding_id', 'report_id', 'objective_id', 'metric_objective_id', 'title', 'description',
        'deliverable', 'done_when', 'first_five_minutes', 'expected_failure',
        'root_cause', 'commercial_impact', 'action_steps', 'worked_example', 'example_source',
        'owner_role', 'resources',
        'timeframe', 'duration_days', 'template_id', 'template_payload', 'degraded', 'degrade_reason',
        'fallback_coaching', 'dependencies', 'impact', 'effort', 'priority', 'kpi_hint',
        'kpi_definition', 'kpi_source', 'baseline', 'target', 'missing_baseline_reason',
        'success_condition', 'stop_condition', 'risks', 'confidence',
    ];

    protected function casts(): array
    {
        return [
            'action_steps' => 'array',
            'worked_example' => 'array',
            'resources' => 'array',
            'dependencies' => 'array',
            'risks' => 'array',
            'fallback_coaching' => 'array',
            'template_payload' => 'array',
            'degraded' => 'boolean',
            'duration_days' => 'integer',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function metricObjective(): BelongsTo
    {
        return $this->belongsTo(Objective::class, 'metric_objective_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecommendationTemplate::class, 'template_id');
    }

    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    public function impactLabel(): string
    {
        return match ($this->impact) {
            'high' => 'أثر عالٍ',
            'medium' => 'أثر متوسط',
            default => 'أثر محدود',
        };
    }

    public function effortLabel(): string
    {
        return match ($this->effort) {
            'high' => 'جهد كبير',
            'medium' => 'جهد متوسط',
            default => 'جهد بسيط',
        };
    }
}
