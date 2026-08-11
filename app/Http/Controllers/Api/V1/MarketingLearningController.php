<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateMarketingExercise;
use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Models\Workspace;
use App\Modules\Learning\MarketingCourseCatalog;
use App\Modules\Learning\MarketingExerciseCompletenessScorer;
use App\Modules\Learning\MarketingLearningRecommender;
use App\Services\Billing\Entitlements;
use App\Support\Billing\FeatureKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class MarketingLearningController extends Controller
{
    public function __construct(
        private readonly MarketingCourseCatalog $catalog,
        private readonly MarketingLearningRecommender $recommender,
        private readonly MarketingExerciseCompletenessScorer $completeness,
        private readonly Entitlements $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$run, $project] = $this->run($request);
        $recommendation = $this->recommender->next($run);
        $next = $recommendation['exercise'] === null ? null : [
            ...$recommendation['exercise'],
            'reason' => $recommendation['reason'],
        ];

        return response()->json([
            'data' => [
                'project' => $this->projectPayload($project),
                'project_choices' => $this->projectChoices($request, $run->workspace),
                'progress' => [
                    'completed' => $run->completed_exercises,
                    'total' => count($this->catalog->exercises()),
                    'average_score' => $run->average_score,
                ],
                'next' => $next,
                'primary_actions' => $next === null ? [[
                    'type' => 'review_progress',
                ]] : [[
                    'type' => 'start_learning_application',
                    'key' => $next['key'],
                ]],
                'lessons' => collect($this->catalog->lessons())->map(fn (array $lesson): array => [
                    'number' => $lesson['number'],
                    'title' => $lesson['title'],
                    'applications_count' => count($lesson['exercises']),
                ])->values()->all(),
            ],
        ]);
    }

    public function show(Request $request, string $exercise): JsonResponse
    {
        $definition = $this->definition($exercise);
        [$run, $project] = $this->run($request);
        $attempt = $run->attemptFor($exercise);
        $run->forceFill(['current_exercise_key' => $exercise])->save();

        return response()->json([
            'data' => [
                ...$this->applicationPayload($definition, $attempt),
                'project' => $this->projectPayload($project),
                'project_choices' => $this->projectChoices($request, $run->workspace),
            ],
        ]);
    }

    public function answer(Request $request, string $exercise, string $question): JsonResponse
    {
        $definition = $this->definition($exercise);

        try {
            $questionDefinition = $this->catalog->question($exercise, $question);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $rules = ($questionDefinition['type'] ?? 'textarea') === 'number'
            ? ['required', 'numeric', 'min:'.(int) ($questionDefinition['min'] ?? 1)]
            : ['required', 'string', 'min:'.(int) ($questionDefinition['min'] ?? 1), 'max:5000'];
        $data = $request->validate(['answer' => $rules]);
        [$run, $project] = $this->run($request);
        $attempt = $run->attemptFor($exercise);

        if (! $this->answerIsEditable($attempt)) {
            return $this->answerLockedResponse();
        }

        $attempt = DB::transaction(function () use ($attempt, $question, $data): ?MarketingExerciseAttempt {
            $current = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $this->answerIsEditable($current)) {
                return null;
            }

            $answers = $current->answers ?? [];
            $answers[$question] = $data['answer'];
            $current->forceFill([
                'answers' => $answers,
                'status' => MarketingExerciseAttempt::STATUS_DRAFT,
            ])->save();

            return $current->refresh();
        });

        if ($attempt === null) {
            return $this->answerLockedResponse();
        }

        $questions = collect($definition['questions'])->pluck('key')->values();
        $position = $questions->search($question);
        $nextQuestion = $position === false ? null : $questions->get($position + 1);

        return response()->json([
            'data' => [
                'attempt' => $this->attemptPayload($attempt),
                'next_question_key' => $nextQuestion,
                'project' => $this->projectPayload($project),
            ],
        ]);
    }

    public function review(Request $request, string $exercise): JsonResponse
    {
        $definition = $this->definition($exercise);
        [$run, $project] = $this->run($request);
        $attempt = $run->attemptFor($exercise);

        if (in_array($attempt->status, [
            MarketingExerciseAttempt::STATUS_QUEUED,
            MarketingExerciseAttempt::STATUS_EVALUATING,
            MarketingExerciseAttempt::STATUS_COMPLETED,
        ], true)) {
            return response()->json([
                'data' => [
                    'attempt' => $this->attemptPayload($attempt),
                    'project' => $this->projectPayload($project),
                ],
            ]);
        }

        $completeness = $this->completeness->score($definition, $attempt->answers ?? []);
        if ($completeness['missing'] !== []) {
            return response()->json([
                'error' => [
                    'code' => 'learning_answers_incomplete',
                    'message' => __('أكمل الإجابات المطلوبة قبل إرسال التطبيق للمراجعة.'),
                    'missing_question' => $completeness['missing'][0],
                ],
            ], 422);
        }

        $queued = DB::transaction(function () use ($attempt, $completeness): ?MarketingExerciseAttempt {
            $current = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (in_array($current->status, [
                MarketingExerciseAttempt::STATUS_QUEUED,
                MarketingExerciseAttempt::STATUS_EVALUATING,
                MarketingExerciseAttempt::STATUS_COMPLETED,
            ], true)) {
                return null;
            }
            $current->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_QUEUED,
                'evaluation_token' => null,
                'evaluation_started_at' => null,
                'completeness_score' => $completeness['score'],
                'failure_reason' => null,
                'submitted_at' => now(),
            ])->save();

            return $current->refresh();
        });

        if ($queued !== null) {
            $this->dispatchReview($queued);
        }

        $attempt = ($queued ?? $attempt)->refresh();

        return response()->json([
            'data' => [
                'attempt' => $this->attemptPayload($attempt),
                'project' => $this->projectPayload($project),
            ],
        ], $queued === null ? 200 : 202);
    }

    /** @return array{0: MarketingLearningRun, 1: ?Project} */
    private function run(Request $request): array
    {
        $user = $request->user();
        $workspace = $user->primaryWorkspace();

        if (! $this->entitlements->allows($workspace, FeatureKey::LEARNING_MARKETING)) {
            abort(response()->json([
                'error' => [
                    'code' => 'feature_not_available',
                    'message' => __('مسار التعلم مفعّل، لكن هذه التطبيقات غير متاحة في باقتك الحالية.'),
                    'feature' => FeatureKey::LEARNING_MARKETING,
                    'action' => 'view_learning_plans',
                ],
            ], 403));
        }

        $project = $this->projectContext($request, $workspace);

        return [MarketingLearningRun::startForWorkspace($workspace, $user, $project), $project];
    }

    private function projectContext(Request $request, Workspace $workspace): ?Project
    {
        $projectId = $request->input('project_id');

        if ($projectId === null || $projectId === '') {
            return null;
        }

        $user = $request->user();
        abort_unless(
            $user->hasBusinessExperience() && $workspace->owner_id === $user->id,
            404,
        );
        abort_unless(is_int($projectId) || (is_string($projectId) && ctype_digit($projectId)), 404);

        return $workspace->projects()->whereKey((int) $projectId)->firstOrFail();
    }

    /** @return list<array{id: int, name: string, slug: string}> */
    private function projectChoices(Request $request, Workspace $workspace): array
    {
        $user = $request->user();

        if (! $user->hasBusinessExperience() || $workspace->owner_id !== $user->id) {
            return [];
        }

        return $workspace->projects()
            ->oldest('id')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Project $project): array => $this->projectPayload($project))
            ->all();
    }

    /** @return array{id: int, name: string, slug: string}|null */
    private function projectPayload(?Project $project): ?array
    {
        if ($project === null) {
            return null;
        }

        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
        ];
    }

    /** @return array<string, mixed> */
    private function definition(string $exercise): array
    {
        try {
            return $this->catalog->exercise($exercise);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    /** @param array<string, mixed> $definition */
    private function applicationPayload(array $definition, MarketingExerciseAttempt $attempt): array
    {
        return [
            'exercise' => [
                'key' => $definition['key'],
                'title' => $definition['title'],
                'purpose' => $definition['purpose'],
                'deliverable' => $definition['deliverable'],
                'duration_minutes' => $definition['duration_minutes'],
                'lesson_number' => $definition['lesson_number'],
                'lesson_title' => $definition['lesson_title'],
                'questions' => collect($definition['questions'])->map(fn (array $question): array => [
                    'key' => $question['key'],
                    'label' => $question['label'],
                    'help' => $question['help'],
                    'example' => $question['example'],
                    'type' => $question['type'],
                    'required' => $question['required'],
                    'min' => $question['min'],
                ])->values()->all(),
            ],
            'attempt' => $this->attemptPayload($attempt),
        ];
    }

    private function attemptPayload(MarketingExerciseAttempt $attempt): array
    {
        return [
            'status' => $attempt->status,
            'answers' => $attempt->answers ?? [],
            'score' => $attempt->final_score,
            'feedback' => $attempt->feedback,
            'failure_reason' => $attempt->failure_reason,
        ];
    }

    private function answerIsEditable(MarketingExerciseAttempt $attempt): bool
    {
        return in_array($attempt->status, [
            MarketingExerciseAttempt::STATUS_DRAFT,
            MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
        ], true);
    }

    private function answerLockedResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'learning_review_in_progress',
                'message' => __('لا يمكن تعديل الإجابات بعدما بدأت مراجعة التطبيق.'),
            ],
        ], 409);
    }

    private function dispatchReview(MarketingExerciseAttempt $attempt): void
    {
        try {
            EvaluateMarketingExercise::dispatch($attempt->id);
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
                'failure_reason' => Str::limit($exception->getMessage(), 1000),
            ])->save();
            Log::warning(__('تعذر إرسال مهمة تعلم التسويق إلى المراجعة.'), [
                'attempt_id' => $attempt->id,
                'exercise_key' => $attempt->exercise_key,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
