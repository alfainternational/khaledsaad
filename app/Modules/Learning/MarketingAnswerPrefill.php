<?php

namespace App\Modules\Learning;

use App\Models\MarketingExerciseAttempt;
use App\Modules\Brain\BrainReader;

class MarketingAnswerPrefill
{
    public function __construct(
        private readonly MarketingCourseCatalog $catalog,
        private readonly BrainReader $brain,
    ) {}

    /**
     * @param  array<string, mixed>  $question
     * @return array{value: mixed, source: string}|null
     */
    public function forQuestion(MarketingExerciseAttempt $attempt, array $question): ?array
    {
        $draftValue = $attempt->answers[$question['key']] ?? null;

        if ($this->present($draftValue)) {
            return ['value' => $draftValue, 'source' => 'draft'];
        }

        $brainKey = $question['brain_key'] ?? null;

        if (! is_string($brainKey) || $brainKey === '') {
            return null;
        }

        $attempt->loadMissing('run.project');

        foreach ($attempt->run->attempts()
            ->where('id', '!=', $attempt->id)
            ->where('status', MarketingExerciseAttempt::STATUS_COMPLETED)
            ->latest('evaluated_at')
            ->get() as $previous) {
            $definition = $this->catalog->exercise($previous->exercise_key);

            foreach ($definition['questions'] as $previousQuestion) {
                if (($previousQuestion['brain_key'] ?? null) !== $brainKey) {
                    continue;
                }

                $value = $previous->answers[$previousQuestion['key']] ?? null;

                if ($this->present($value)) {
                    return ['value' => $value, 'source' => 'completed_exercise'];
                }
            }
        }

        if ($attempt->run->project === null) {
            return null;
        }

        $value = $this->brain->value($attempt->run->project, $brainKey);

        return $this->present($value) ? ['value' => $value, 'source' => 'project'] : null;
    }

    private function present(mixed $value): bool
    {
        return ! ($value === null || $value === '' || $value === []);
    }
}
