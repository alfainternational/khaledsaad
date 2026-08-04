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
        if (! $this->claim($attempt->id)) {
            return $attempt->fresh();
        }

        $attempt = MarketingExerciseAttempt::query()
            ->with(['run.project', 'reviews'])
            ->findOrFail($attempt->id);
        $exercise = $this->catalog->exercise($attempt->exercise_key);

        try {
            $feedback = $this->runner->run(AIRequest::json(
                messages: $this->messages($attempt, $exercise),
                schema: MarketingExerciseEvaluationSchema::schema(),
                tier: 'standard',
                stage: 'marketing_exercise_evaluation',
            ));

            $this->assertFeedbackKeys($exercise, $feedback);

            return $this->storeSuccessfulReview($attempt, $exercise, $feedback);
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
                'failure_reason' => Str::limit($exception->getMessage(), 1000),
            ])->save();

            Log::warning('تعذرت مراجعة مهمة من مسار تعلم التسويق.', [
                'attempt_id' => $attempt->id,
                'exercise_key' => $attempt->exercise_key,
                'reason' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $attempt->refresh();
        }
    }

    private function claim(int $attemptId): bool
    {
        return DB::transaction(function () use ($attemptId): bool {
            $attempt = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attemptId);

            if ($attempt->status !== MarketingExerciseAttempt::STATUS_QUEUED) {
                return false;
            }

            $attempt->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_EVALUATING,
                'failure_reason' => null,
            ])->save();

            return true;
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
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)],
        ];
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
     */
    private function storeSuccessfulReview(
        MarketingExerciseAttempt $attempt,
        array $exercise,
        array $feedback,
    ): MarketingExerciseAttempt {
        return DB::transaction(function () use ($attempt, $exercise, $feedback): MarketingExerciseAttempt {
            $attempt = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $revision = ((int) $attempt->reviews()->max('revision')) + 1;
            $aiScore = max(0, min(100, (int) $feedback['overall_score']));
            $completeness = max(0, min(100, (int) $attempt->completeness_score));
            $finalScore = (int) round(($completeness * 0.30) + ($aiScore * 0.70));

            $attempt->reviews()->create([
                'revision' => $revision,
                'answers' => $attempt->answers,
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
