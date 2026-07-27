<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recommendation extends Model
{
    protected $fillable = [
        'finding_id', 'report_id', 'title', 'description',
        'root_cause', 'commercial_impact', 'action_steps', 'owner_role', 'resources',
        'timeframe', 'dependencies', 'impact', 'effort', 'priority', 'kpi_hint',
        'kpi_definition', 'kpi_source', 'baseline', 'target', 'missing_baseline_reason',
        'success_condition', 'stop_condition', 'risks', 'confidence',
    ];

    protected function casts(): array
    {
        return ['action_steps' => 'array', 'resources' => 'array', 'dependencies' => 'array', 'risks' => 'array'];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
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
