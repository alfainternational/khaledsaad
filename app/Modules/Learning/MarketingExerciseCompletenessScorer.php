<?php

namespace App\Modules\Learning;

class MarketingExerciseCompletenessScorer
{
    /**
     * @param  array<string, mixed>  $exercise
     * @param  array<string, mixed>  $answers
     * @return array{score: int, missing: array<int, string>, answered: int, required: int}
     */
    public function score(array $exercise, array $answers): array
    {
        $required = collect($exercise['questions'] ?? [])
            ->filter(fn (array $question) => (bool) ($question['required'] ?? false))
            ->values();

        if ($required->isEmpty()) {
            return ['score' => 100, 'missing' => [], 'answered' => 0, 'required' => 0];
        }

        $missing = $required
            ->reject(fn (array $question) => $this->valid($question, $answers[$question['key']] ?? null))
            ->pluck('key')
            ->values()
            ->all();
        $answered = $required->count() - count($missing);

        return [
            'score' => (int) round(($answered / $required->count()) * 100),
            'missing' => $missing,
            'answered' => $answered,
            'required' => $required->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function valid(array $question, mixed $value): bool
    {
        $minimum = (int) ($question['min'] ?? 1);

        if (($question['type'] ?? 'textarea') === 'number') {
            return is_numeric($value) && (float) $value >= $minimum;
        }

        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => filled($item))) >= max(1, $minimum);
        }

        return is_string($value) && mb_strlen(trim($value)) >= $minimum;
    }
}
