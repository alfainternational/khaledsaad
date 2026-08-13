<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateMarketingExercise;
use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Models\Workspace;
use App\Modules\Learning\MarketingAnswerPrefill;
use App\Modules\Learning\MarketingCourseCatalog;
use App\Modules\Learning\MarketingExerciseCompletenessScorer;
use App\Modules\Learning\MarketingLearningRecommender;
use App\Modules\Learning\MarketingLessonAssistComposer;
use App\Services\Billing\Entitlements;
use App\Support\Billing\FeatureKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class MarketingCourseController extends Controller
{
    public function __construct(
        private readonly MarketingCourseCatalog $catalog,
        private readonly MarketingLearningRecommender $recommender,
        private readonly MarketingAnswerPrefill $prefill,
        private readonly MarketingExerciseCompletenessScorer $completeness,
        private readonly Entitlements $entitlements,
        private readonly MarketingLessonAssistComposer $lessonAssist,
    ) {}

    public function index(Request $request): View
    {
        [$run, $project] = $this->run($request);
        $attempts = $run->attempts()->get()->keyBy('exercise_key');
        $recommendation = $this->recommender->next($run);
        $lessons = collect($this->catalog->lessons())->map(function (array $lesson) use ($attempts): array {
            $lesson['exercises'] = collect($lesson['exercises'])->map(function (array $exercise) use ($attempts): array {
                $attempt = $attempts->get($exercise['key']);

                return [
                    ...$exercise,
                    'status' => $attempt?->status ?? 'not_started',
                    'score' => $attempt?->final_score,
                    'status_label' => match ($attempt?->status) {
                        MarketingExerciseAttempt::STATUS_COMPLETED => __('مكتملة'),
                        MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING => __('قيد المراجعة'),
                        MarketingExerciseAttempt::STATUS_REVIEW_FAILED => __('تحتاج إعادة المراجعة'),
                        MarketingExerciseAttempt::STATUS_DRAFT => __('بدأتها'),
                        default => __('لم تبدأ'),
                    },
                ];
            })->all();

            return $lesson;
        })->all();

        return view('app.learning.marketing.index', [
            'project' => $project,
            'run' => $run,
            'lessons' => $lessons,
            'recommendation' => $recommendation,
            'exerciseCount' => count($this->catalog->exercises()),
        ]);
    }

    public function exercise(Request $request, string $exercise): View
    {
        [$run, $project] = $this->run($request);
        $definition = $this->definition($exercise);
        $attempt = $run->attemptFor($exercise);
        $step = max(1, min((int) $request->integer('step', 1), count($definition['questions'])));
        $question = $definition['questions'][$step - 1];
        $run->forceFill(['current_exercise_key' => $exercise])->save();

        return view('app.learning.marketing.exercise', [
            'project' => $project,
            'exercise' => $definition,
            'attempt' => $attempt,
            'step' => $step,
            'stepCount' => count($definition['questions']),
            'question' => $question,
            'answer' => $this->prefill->forQuestion($attempt, $question),
        ]);
    }

    public function save(Request $request, string $exercise): RedirectResponse
    {
        [$run] = $this->run($request);
        $definition = $this->definition($exercise);
        $step = max(1, min((int) $request->integer('step', 1), count($definition['questions'])));
        $question = $definition['questions'][$step - 1];
        $minimum = (int) ($question['min'] ?? 1);
        $rules = ($question['type'] ?? 'textarea') === 'number'
            ? ['required', 'numeric', 'min:'.$minimum]
            : ['required', 'string', 'min:'.$minimum, 'max:5000'];
        $data = $request->validate(['answer' => $rules], [
            'answer.required' => __('اكتب إجابتك حتى نستطيع متابعة المهمة.'),
            'answer.min' => __('أضف قليلًا من التفاصيل حتى تكون الإجابة مفيدة عند مراجعتها.'),
            'answer.max' => __('اختصر الإجابة إلى أهم ما يحتاجه القرار.'),
            'answer.numeric' => __('اكتب الرقم فقط في هذا الحقل.'),
        ]);
        $attempt = $run->attemptFor($exercise);

        if (in_array($attempt->status, [MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING], true)) {
            return redirect()->route('app.learning.marketing.course.result', $exercise)
                ->with('status', __('المراجعة تعمل الآن. بعد ظهور النتيجة يمكنك تعديل إجاباتك وتحسينها.'));
        }

        $saved = DB::transaction(function () use ($attempt, $question, $data): bool {
            $current = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (in_array($current->status, [MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING], true)) {
                return false;
            }

            $answers = $current->answers ?? [];
            $changed = ($answers[$question['key']] ?? null) !== $data['answer'];
            $answers[$question['key']] = $data['answer'];
            $current->forceFill([
                'answers' => $answers,
                'status' => $changed ? MarketingExerciseAttempt::STATUS_DRAFT : $current->status,
            ])->save();

            return true;
        });

        if (! $saved) {
            return redirect()->route('app.learning.marketing.course.result', $exercise);
        }

        return redirect()->route('app.learning.marketing.course.exercise', [
            'exercise' => $exercise,
            'step' => min($step + 1, count($definition['questions'])),
        ])->with('status', $step < count($definition['questions']) ? __('حفظنا إجابتك. نكمل بالسؤال التالي.') : __('حفظنا إجابتك. المهمة جاهزة للمراجعة.'));
    }

    public function submit(Request $request, string $exercise): RedirectResponse
    {
        [$run] = $this->run($request);
        $definition = $this->definition($exercise);
        $attempt = $run->attemptFor($exercise);

        if (in_array($attempt->status, [MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING, MarketingExerciseAttempt::STATUS_COMPLETED], true)) {
            return redirect()->route('app.learning.marketing.course.result', $exercise);
        }

        $completeness = $this->completeness->score($definition, $attempt->answers ?? []);
        if ($completeness['missing'] !== []) {
            $missingKey = $completeness['missing'][0];
            $step = collect($definition['questions'])->search(fn (array $question) => $question['key'] === $missingKey);

            return redirect()->route('app.learning.marketing.course.exercise', [
                'exercise' => $exercise,
                'step' => ((int) $step) + 1,
            ])->with('error', __('بقيت إجابة واحدة أو أكثر. أكملها ثم نراجع المهمة كاملة.'));
        }

        $answersAtSubmission = $attempt->answers;
        $queued = DB::transaction(function () use ($attempt, $completeness, $answersAtSubmission): bool {
            $current = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($current->answers !== $answersAtSubmission || in_array($current->status, [MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING, MarketingExerciseAttempt::STATUS_COMPLETED], true)) {
                return false;
            }
            $current->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_QUEUED,
                'evaluation_token' => null,
                'evaluation_started_at' => null,
                'completeness_score' => $completeness['score'],
                'failure_reason' => null,
                'submitted_at' => now(),
            ])->save();

            return true;
        });

        if ($queued) {
            $attempt->refresh();
            $this->dispatchReview($attempt);
        }

        return redirect()->route('app.learning.marketing.course.result', $exercise);
    }

    public function result(Request $request, string $exercise): View
    {
        [$run, $project] = $this->run($request);
        $definition = $this->definition($exercise);
        $attempt = $run->attempts()->where('exercise_key', $exercise)->firstOrFail();

        return view('app.learning.marketing.result', [
            'project' => $project,
            'exercise' => $definition,
            'attempt' => $attempt,
            'feedbackByKey' => collect($attempt->feedback['input_feedback'] ?? [])->keyBy('key'),
            'recommendation' => $this->recommender->next($run),
            'reviewHistory' => $attempt->reviews()->latest('revision')->get(),
        ]);
    }

    public function retry(Request $request, string $exercise): RedirectResponse
    {
        [$run] = $this->run($request);
        $this->definition($exercise);
        $attempt = $run->attempts()->where('exercise_key', $exercise)->firstOrFail();
        $retryAttempt = DB::transaction(function () use ($attempt): ?MarketingExerciseAttempt {
            $current = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($current->status !== MarketingExerciseAttempt::STATUS_REVIEW_FAILED) {
                return null;
            }
            $current->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_QUEUED,
                'evaluation_token' => null,
                'evaluation_started_at' => null,
                'failure_reason' => null,
                'submitted_at' => now(),
            ])->save();

            return $current;
        });

        if ($retryAttempt !== null) {
            $this->dispatchReview($retryAttempt);
        }

        return redirect()->route('app.learning.marketing.course.result', $exercise);
    }

    public function assist(Request $request, string $exercise, string $question): JsonResponse
    {
        [$run] = $this->run($request);
        $this->definition($exercise);

        try {
            $this->catalog->question($exercise, $question);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $attempt = $run->attemptFor($exercise);
        $data = $request->validate(['section_id' => ['nullable', 'string', 'max:100']]);

        return response()->json([
            'data' => $this->lessonAssist->compose($attempt, $question, $data['section_id'] ?? null),
        ], options: JSON_UNESCAPED_UNICODE);
    }

    /** @return array{0: MarketingLearningRun, 1: ?Project} */
    private function run(Request $request): array
    {
        $user = $request->user();
        $workspace = $user->primaryWorkspace();
        abort_unless($this->entitlements->allows($workspace, FeatureKey::LEARNING_MARKETING), 403);
        $project = $this->projectContext($request, $workspace);

        return [MarketingLearningRun::startForWorkspace($workspace, $user, $project), $project];
    }

    private function projectContext(Request $request, Workspace $workspace): ?Project
    {
        $slug = trim((string) $request->query('project'));

        return $slug === '' ? null : $workspace->projects()->where('slug', $slug)->firstOrFail();
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

    private function dispatchReview(MarketingExerciseAttempt $attempt): bool
    {
        try {
            $pending = EvaluateMarketingExercise::dispatch($attempt->id);
            unset($pending);

            return true;
        } catch (Throwable $exception) {
            $attempt->refresh();
            if ($attempt->status === MarketingExerciseAttempt::STATUS_QUEUED) {
                $attempt->forceFill([
                    'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
                    'failure_reason' => Str::limit($exception->getMessage(), 1000),
                ])->save();
            }
            Log::warning(__('تعذر إرسال مهمة تعلم التسويق إلى المراجعة.'), [
                'attempt_id' => $attempt->id,
                'exercise_key' => $attempt->exercise_key,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
