<?php

namespace App\Jobs;

use App\Models\MarketingExerciseAttempt;
use App\Modules\Learning\MarketingExerciseEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class EvaluateMarketingExercise implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $attemptId) {}

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(MarketingExerciseEvaluator $evaluator): void
    {
        $attempt = MarketingExerciseAttempt::query()->find($this->attemptId);

        if ($attempt !== null) {
            $evaluator->evaluate($attempt);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $attempt = MarketingExerciseAttempt::query()->find($this->attemptId);

        if ($attempt === null || ! in_array($attempt->status, [
            MarketingExerciseAttempt::STATUS_QUEUED,
            MarketingExerciseAttempt::STATUS_EVALUATING,
        ], true)) {
            return;
        }

        $attempt->forceFill([
            'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
            'evaluation_token' => null,
            'evaluation_started_at' => null,
            'failure_reason' => Str::limit($exception?->getMessage() ?? 'تعذر إكمال المراجعة بعد المحاولات المتاحة.', 1000),
        ])->save();
    }
}
