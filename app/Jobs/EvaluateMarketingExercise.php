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
}
