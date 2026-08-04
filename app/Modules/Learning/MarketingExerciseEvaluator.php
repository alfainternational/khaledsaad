<?php

namespace App\Modules\Learning;

use App\Exceptions\AIInvalidOutputException;
use App\Models\MarketingExerciseAttempt;
use App\Modules\Brain\BrainReader;
use App\Modules\Brain\BrainWriter;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MarketingExerciseEvaluator
{
    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly MarketingCourseCatalog $catalog,
        private readonly BrainReader $brain,
        private readonly BrainWriter $brainWriter,
    ) {}

    public function evaluate(MarketingExerciseAttempt $attempt): MarketingExerciseAttempt
    {
        $evaluationToken = $this->claim($attempt->id);

        if ($evaluationToken === null) {
            return $attempt->fresh();
        }

        $attempt = MarketingExerciseAttempt::query()
            ->with(['run.project', 'reviews'])
            ->findOrFail($attempt->id);
        $exercise = $this->catalog->exercise($attempt->exercise_key);
        $evaluatedAnswers = $attempt->answers;

        try {
            $feedback = $this->runner->run(AIRequest::json(
                messages: $this->messages($attempt, $exercise),
                schema: MarketingExerciseEvaluationSchema::schema(),
                tier: 'standard',
                stage: 'marketing_exercise_evaluation',
            ));

            $this->assertFeedbackKeys($exercise, $feedback);

            return $this->storeSuccessfulReview($attempt, $exercise, $feedback, $evaluatedAnswers, $evaluationToken);
        } catch (Throwable $exception) {
            $current = MarketingExerciseAttempt::query()->findOrFail($attempt->id);

            if ($current->status === MarketingExerciseAttempt::STATUS_EVALUATING
                && hash_equals((string) $current->evaluation_token, $evaluationToken)) {
                $current->forceFill([
                    'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
                    'evaluation_token' => null,
                    'evaluation_started_at' => null,
                    'failure_reason' => Str::limit($exception->getMessage(), 1000),
                ])->save();
            }

            Log::warning('تعذرت مراجعة مهمة من مسار تعلم التسويق.', [
                'attempt_id' => $attempt->id,
                'exercise_key' => $attempt->exercise_key,
                'reason' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $current->refresh();
        }
    }

    private function claim(int $attemptId): ?string
    {
        return DB::transaction(function () use ($attemptId): ?string {
            $attempt = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attemptId);

            if (! in_array($attempt->status, [
                MarketingExerciseAttempt::STATUS_QUEUED,
                MarketingExerciseAttempt::STATUS_EVALUATING,
            ], true)) {
                return null;
            }

            $token = (string) Str::uuid();

            $attempt->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_EVALUATING,
                'evaluation_token' => $token,
                'evaluation_started_at' => now(),
                'failure_reason' => null,
            ])->save();

            return $token;
        });
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(MarketingExerciseAttempt $attempt, array $exercise): array
    {
        $project = $attempt->run->project;
        $keys = collect($exercise['brain_dependencies'])
            ->merge(collect($exercise['questions'])->pluck('brain_key')->filter())
            ->unique()
            ->values();
        $facts = $this->brain->facts($project)
            ->toBase()
            ->only($keys->all())
            ->map(fn ($fact) => [
                'value' => $fact->value_json['value'] ?? null,
                'evidence_level' => $fact->evidence_level->value,
                'observed_at' => $fact->observed_at?->toIso8601String(),
            ])
            ->all();

        $questions = collect($exercise['questions'])->map(fn (array $question) => [
            'key' => $question['key'],
            'question' => $question['label'],
            'rubric' => $question['rubric'],
            'answer' => $attempt->answers[$question['key']] ?? null,
        ])->all();

        return [
            ['role' => 'system', 'content' => MarketingExerciseEvaluationSchema::instructions()],
            ['role' => 'user', 'content' => json_encode([
                'project' => [
                    'name' => $project->name,
                    'sector' => $project->sector,
                    'industry' => $project->industry,
                    'known_facts' => $facts,
                ],
                'exercise' => [
                    'title' => $exercise['title'],
                    'purpose' => $exercise['purpose'],
                    'expected_deliverable' => $exercise['deliverable'],
                    'questions' => $questions,
                    'related_previous_answers' => $this->relatedPreviousAnswers($attempt, $exercise),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)],
        ];
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @return array<int, array<string, mixed>>
     */
    private function relatedPreviousAnswers(MarketingExerciseAttempt $attempt, array $exercise): array
    {
        $relevantKeys = collect($exercise['brain_dependencies'] ?? [])
            ->merge(collect($exercise['questions'])->pluck('brain_key')->filter())
            ->unique();

        if ($relevantKeys->isEmpty()) {
            return [];
        }

        return $attempt->run->attempts()
            ->where('id', '!=', $attempt->id)
            ->where('status', MarketingExerciseAttempt::STATUS_COMPLETED)
            ->latest('evaluated_at')
            ->get()
            ->map(function (MarketingExerciseAttempt $previous) use ($relevantKeys): ?array {
                $definition = $this->catalog->exercise($previous->exercise_key);
                $questions = collect($definition['questions'])
                    ->filter(fn (array $question) => $relevantKeys->contains($question['brain_key'] ?? null))
                    ->map(fn (array $question) => [
                        'question' => $question['label'],
                        'answer' => $previous->answers[$question['key']] ?? null,
                    ])
                    ->filter(fn (array $answer) => filled($answer['answer']))
                    ->values()
                    ->all();

                return $questions === [] ? null : [
                    'exercise_title' => $definition['title'],
                    'answers' => $questions,
                ];
            })
            ->filter()
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @param  array<string, mixed>  $feedback
     */
    private function assertFeedbackKeys(array $exercise, array $feedback): void
    {
        $expected = collect($exercise['questions'])->pluck('key')->sort()->values()->all();
        $actual = collect($feedback['input_feedback'] ?? [])->pluck('key')->sort()->values()->all();

        if ($actual !== $expected) {
            throw new AIInvalidOutputException(
                'المراجعة لم تُعد درجة لكل إجابة محفوظة.',
                ['input_feedback keys do not match exercise questions'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @param  array<string, mixed>  $feedback
     * @param  array<string, mixed>  $evaluatedAnswers
     */
    private function storeSuccessfulReview(
        MarketingExerciseAttempt $attempt,
        array $exercise,
        array $feedback,
        array $evaluatedAnswers,
        string $evaluationToken,
    ): MarketingExerciseAttempt {
        return DB::transaction(function () use ($attempt, $exercise, $feedback, $evaluatedAnswers, $evaluationToken): MarketingExerciseAttempt {
            $attempt = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== MarketingExerciseAttempt::STATUS_EVALUATING
                || ! hash_equals((string) $attempt->evaluation_token, $evaluationToken)
                || $attempt->answers !== $evaluatedAnswers) {
                throw new AIInvalidOutputException(
                    'تغيرت الإجابات أثناء المراجعة، لذلك لم نربط النتيجة بها.',
                    ['attempt state or answers changed during evaluation'],
                );
            }

            $revision = ((int) $attempt->reviews()->max('revision')) + 1;
            $aiScore = max(0, min(100, (int) $feedback['overall_score']));
            $completeness = max(0, min(100, (int) $attempt->completeness_score));
            $finalScore = (int) round(($completeness * 0.30) + ($aiScore * 0.70));

            $attempt->reviews()->create([
                'revision' => $revision,
                'answers' => $evaluatedAnswers,
                'completeness_score' => $completeness,
                'ai_score' => $aiScore,
                'final_score' => $finalScore,
                'feedback' => $feedback,
                'catalog_version' => $this->catalog->version(),
                'reviewed_at' => now(),
            ]);

            $attempt->forceFill([
                'revision' => $revision,
                'status' => MarketingExerciseAttempt::STATUS_COMPLETED,
                'evaluation_token' => null,
                'evaluation_started_at' => null,
                'ai_score' => $aiScore,
                'final_score' => $finalScore,
                'feedback' => $feedback,
                'failure_reason' => null,
                'evaluated_at' => now(),
            ])->save();

            $this->recordUserAnswers($attempt, $exercise, $revision);
            $attempt->run->refreshProgress(count($this->catalog->exercises()));

            return $attempt->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $exercise
     */
    private function recordUserAnswers(MarketingExerciseAttempt $attempt, array $exercise, int $revision): void
    {
        foreach ($exercise['questions'] as $question) {
            $brainKey = $question['brain_key'] ?? null;
            $value = $attempt->answers[$question['key']] ?? null;

            if (! is_string($brainKey) || $brainKey === '' || blank($value)) {
                continue;
            }

            $this->brainWriter->record(
                project: $attempt->run->project,
                key: $brainKey,
                value: $value,
                level: EvidenceLevel::Inferred,
                sourceModule: 'marketing_learning',
                sourceReference: "attempt:{$attempt->id}:revision:{$revision}",
                metadata: [
                    'exercise_key' => $attempt->exercise_key,
                    'question_key' => $question['key'],
                ],
            );
        }
    }
}
