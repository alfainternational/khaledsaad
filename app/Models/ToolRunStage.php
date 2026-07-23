<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolRunStage extends Model
{
    protected $fillable = [
        'tool_run_id', 'key', 'label', 'status', 'sort_order', 'attempts',
        'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => 'running',
            'started_at' => $this->started_at ?? now(),
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    public function markCompleted(): void
    {
        $this->forceFill(['status' => 'completed', 'completed_at' => now(), 'error' => null])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['status' => 'failed', 'completed_at' => now(), 'error' => $error])->save();
    }
}
