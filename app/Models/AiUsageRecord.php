<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageRecord extends Model
{
    protected $fillable = [
        'tool_run_id', 'stage', 'provider', 'model', 'input_tokens',
        'output_tokens', 'latency_ms', 'cost_usd', 'status',
    ];

    protected function casts(): array
    {
        return ['cost_usd' => 'float'];
    }

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }
}
