<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketingExerciseAttempt extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_EVALUATING = 'evaluating';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REVIEW_FAILED = 'review_failed';

    protected $fillable = [
        'marketing_learning_run_id', 'exercise_key', 'revision', 'answers',
        'status', 'completeness_score', 'ai_score', 'final_score', 'feedback',
        'failure_reason', 'submitted_at', 'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'feedback' => 'array',
            'submitted_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MarketingLearningRun::class, 'marketing_learning_run_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MarketingExerciseReview::class);
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(MarketingExerciseReview::class)->latestOfMany('revision');
    }
}
