<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STATUS_TODO = 'todo';

    public const STATUS_DOING = 'doing';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'project_id', 'recommendation_id', 'owner_id', 'title', 'description',
        'status', 'priority', 'impact', 'effort', 'due_date', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DOING => 'قيد التنفيذ',
            self::STATUS_DONE => 'منجزة',
            default => 'لم تبدأ',
        };
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== self::STATUS_DONE
            && $this->due_date->isPast();
    }
}
