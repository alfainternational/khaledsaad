<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kpi extends Model
{
    protected $fillable = [
        'project_id', 'task_id', 'name', 'unit', 'baseline', 'target', 'frequency',
    ];

    protected function casts(): array
    {
        return [
            'baseline' => 'float',
            'target' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(KpiEntry::class)->orderBy('recorded_at');
    }

    public function latestValue(): ?float
    {
        return $this->entries()->latest('recorded_at')->value('value');
    }

    /**
     * نسبة تحقق الهدف بين خط الأساس والهدف — تُظهر التقدم لا القيمة المطلقة.
     */
    public function attainmentPercent(): ?int
    {
        $latest = $this->latestValue();

        if ($latest === null || $this->target === null || $this->baseline === null) {
            return null;
        }

        $span = $this->target - $this->baseline;

        if (abs($span) < 0.00001) {
            return 100;
        }

        return max(0, min(100, (int) round((($latest - $this->baseline) / $span) * 100)));
    }
}
