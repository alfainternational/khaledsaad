<?php

namespace App\Modules\Learning;

use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Support\Billing\FeatureKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketingCourseGalleryPresenter
{
    public function __construct(
        private readonly MarketingCourseCatalog $catalog,
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(?User $user): array
    {
        $accessState = 'guest';
        $attempts = collect();
        $progressUnavailable = false;

        if ($user !== null) {
            $workspace = $user->primaryWorkspace();
            $accessState = $this->entitlements->allows($workspace, FeatureKey::LEARNING_MARKETING)
                ? 'entitled'
                : 'locked';

            if ($accessState === 'entitled') {
                try {
                    $attempts = MarketingLearningRun::startForWorkspace($workspace, $user)
                        ->attempts()
                        ->get()
                        ->keyBy('exercise_key');
                } catch (Throwable $exception) {
                    $progressUnavailable = true;
                    Log::warning('تعذر تحميل تقدم معرض تعلم التسويق.', [
                        'user_id' => $user->id,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $lessons = collect($this->catalog->lessons())
            ->map(fn (array $lesson): array => $this->lesson($lesson, $attempts, $accessState))
            ->values()
            ->all();
        $exerciseCount = count($this->catalog->exercises());
        $completedAttempts = $attempts->filter(
            fn (MarketingExerciseAttempt $attempt): bool => $attempt->status === MarketingExerciseAttempt::STATUS_COMPLETED,
        );
        $scoredAttempts = $completedAttempts->filter(
            fn (MarketingExerciseAttempt $attempt): bool => $attempt->final_score !== null,
        );

        return [
            'enabled' => true,
            'access_state' => $accessState,
            'authenticated' => $user !== null,
            'progress_unavailable' => $progressUnavailable,
            'lessons' => $lessons,
            'lesson_count' => count($lessons),
            'exercise_count' => $exerciseCount,
            'completed_count' => $completedAttempts->count(),
            'remaining_count' => max(0, $exerciseCount - $completedAttempts->count()),
            'average_score' => $scoredAttempts->isEmpty()
                ? null
                : (int) round((float) $scoredAttempts->avg('final_score')),
        ];
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @param  Collection<string, MarketingExerciseAttempt>  $attempts
     * @return array<string, mixed>
     */
    private function lesson(array $lesson, Collection $attempts, string $accessState): array
    {
        $exercises = collect($lesson['exercises'])
            ->map(fn (array $exercise): array => $this->exercise($exercise, $attempts->get($exercise['key']), $accessState))
            ->values()
            ->all();
        $completed = collect($exercises)->where('status', MarketingExerciseAttempt::STATUS_COMPLETED)->count();

        return [
            'number' => (int) $lesson['number'],
            'title' => (string) $lesson['title'],
            'source_url' => (string) $lesson['source_url'],
            'exercise_count' => count($exercises),
            'completed_count' => $completed,
            'open' => (int) $lesson['number'] === 1,
            'exercises' => $exercises,
        ];
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @return array<string, mixed>
     */
    private function exercise(array $exercise, ?MarketingExerciseAttempt $attempt, string $accessState): array
    {
        $status = $attempt?->status ?? 'not_started';

        if ($accessState === 'locked') {
            $actionLabel = 'اطّلع على الباقات';
            $actionUrl = route('app.billing');
        } elseif ($accessState === 'guest') {
            $actionLabel = 'سجّل وابدأ';
            $actionUrl = route('app.learning.marketing.course.exercise', $exercise['key']);
        } else {
            [$actionLabel, $actionUrl] = match ($status) {
                MarketingExerciseAttempt::STATUS_COMPLETED => [
                    'افتح النتيجة',
                    route('app.learning.marketing.course.result', $exercise['key']),
                ],
                MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING => [
                    'تابع المراجعة',
                    route('app.learning.marketing.course.result', $exercise['key']),
                ],
                MarketingExerciseAttempt::STATUS_REVIEW_FAILED => [
                    'أعد المراجعة',
                    route('app.learning.marketing.course.result', $exercise['key']),
                ],
                MarketingExerciseAttempt::STATUS_DRAFT => [
                    'أكمل',
                    route('app.learning.marketing.course.exercise', $exercise['key']),
                ],
                default => [
                    'ابدأ',
                    route('app.learning.marketing.course.exercise', $exercise['key']),
                ],
            };
        }

        return [
            'key' => (string) $exercise['key'],
            'title' => (string) $exercise['title'],
            'purpose' => (string) $exercise['purpose'],
            'duration_minutes' => (int) $exercise['duration_minutes'],
            'deliverable' => (string) $exercise['deliverable'],
            'status' => $status,
            'status_label' => match ($status) {
                MarketingExerciseAttempt::STATUS_COMPLETED => 'مكتملة',
                MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING => 'قيد المراجعة',
                MarketingExerciseAttempt::STATUS_REVIEW_FAILED => 'تحتاج إعادة المراجعة',
                MarketingExerciseAttempt::STATUS_DRAFT => 'بدأتها',
                default => 'لم تبدأ',
            },
            'score' => $attempt?->final_score,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
        ];
    }
}
