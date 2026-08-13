<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateGap extends Model
{
    protected $fillable = ['objective_id', 'occurrences', 'last_seen_at', 'missing_context'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'missing_context' => 'array'];
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }
}
