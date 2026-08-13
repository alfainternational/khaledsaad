<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringItem extends Model
{
    protected $fillable = [
        'report_id', 'item_key', 'tier', 'weight', 'coefficient',
        'points', 'answer_value', 'answer_quote',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'coefficient' => 'float',
            'points' => 'float',
            'answer_value' => 'array',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
