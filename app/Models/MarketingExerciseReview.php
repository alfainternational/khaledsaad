<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingExerciseReview extends Model
{
    protected $fillable = [
        'marketing_exercise_attempt_id', 'revision', 'answers',
        'completeness_score', 'ai_score', 'final_score', 'feedback',
        'catalog_version', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'feedback' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(MarketingExerciseAttempt::class, 'marketing_exercise_attempt_id');
    }
}
