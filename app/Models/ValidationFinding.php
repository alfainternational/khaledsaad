<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationFinding extends Model
{
    protected $fillable = [
        'report_id', 'rule_code', 'severity', 'path', 'message',
        'suggested_action', 'meta', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'resolved_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
