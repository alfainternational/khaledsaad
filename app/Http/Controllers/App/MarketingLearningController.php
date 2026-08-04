<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateMarketingExercise;
use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Modules\Learning\MarketingAnswerPrefill;
use App\Modules\Learning\MarketingCourseCatalog;
use App\Modules\Learning\MarketingExerciseCompletenessScorer;
use App\Modules\Learning\MarketingLearningRecommender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class MarketingLearningController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly MarketingCourseCatalog $catalog,
        private readonly MarketingLearningRecommender $recommender,
        private readonly MarketingAnswerPrefill $prefill,
        private readonly MarketingExerciseCompletenessScorer $completeness,
    ) {}

    public function home(Request $request): RedirectResponse
    {
        $project = Project::query()
            ->whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->latest('id')
            ->first();

        if ($project === null) {
            return redirect()->route('app.projects.create')
                ->with('status', 'أضف مشروعك أولًا، ثم نرتب لك مسار الدروس المناسب له.');
        }

        return redirect()->route('app.learning.marketing.index', $project);
    }

    public function index(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);
        $run = MarketingLearningRun::startFor($project, $request->user());
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
                        MarketingExerciseAttempt::STATUS_COMPLETED => 'مكتملة',
                        MarketingExerciseAttempt::STATUS_QUEUED, MarketingExerciseAttempt::STATUS_EVALUATING => 'قيد المراجعة',
                        MarketingExerciseAttempt::STATUS_REVIEW_FAILED => 'تحتاج إعادة المراجعة',
                        MarketingExerciseAttempt::STATUS_DRAFT => 'بدأتها',
                        default => 'لم تبدأ',
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

    public function exercise(Request $request, Project $project, string $exercise): View
    {
        $this->authorizeProject($request, $project);
        $definition = $this->definition($exercise);
        $run = MarketingLearningRun::startFor($project, $request->user());
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

    public function save(Request $request, Project $project, string $exercise): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $definition = $this->definition($exercise);
        $step = max(1, min((int) $request->integer('step', 1), count($definition['questions'])));
        $question = $definition['questions'][$step - 1];
        $minimum = (int) ($question['min'] ?? 1);
        $rules = ($question['type'] ?? 'textarea') === 'number'
            ? ['required', 'numeric', 'min:'.$minimum]
            : ['required', 'string', 'min:'.$minimum, 'max:5000'];
        $messages = [
            'answer.required' => 'اكتب إجابتك حتى نستطيع متابعة المهمة.',
            'answer.min' => 'أضف قليلًا من التفاصيل حتى تكون الإجابة مفيدة عند مراجعتها.',
            'answer.max' => 'اختصر الإجابة إلى أهم ما يحتاجه القرار.',
            'answer.numeric' => 'اكتب الرقم فقط في هذا الحقل.',
        ];
        $data = $request->validate(['answer' => $rules], $messages);
        $run = MarketingLearningRun::startFor($project, $request->user());
        $attempt = $run->attemptFor($exercise);

        if (in_array($attempt->status, [
            MarketingExerciseAttempt::STATUS_QUEUED,
            MarketingExerciseAttempt::STATUS_EVALUATING,
        ], true)) {
            return redirect()->route('app.learning.marketing.result', [$project, $exercise])
                ->with('status', 'المراجعة تعمل الآن. بعد ظهور النتيجة يمكنك تعديل إجاباتك وتحسينها.');
        }

        $saved = DB::transaction(function () use ($attempt, $question, $data): bool {
            $current = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (in_array($current->status, [
                MarketingExerciseAttempt::STATUS_QUEUED,
                MarketingExerciseAttempt::STATUS_EVALUATING,
            ], true)) {
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
            return redirect()->route('app.learning.marketing.result', [$project, $exercise])
                ->with('status', 'المراجعة بدأت قبل حفظ التعديل. انتظر النتيجة ثم أعد تعديل الإجابة.');
        }

        $next = min($step + 1, count($definition['questions']));

        return redirect()->route('app.learning.marketing.exercise', [
            $project,
            $exercise,
            'step' => $next,
        ])->with('status', $step < count($definition['questions']) ? 'حفظنا إجابتك. نكمل بالسؤال التالي.' : 'حفظنا إجابتك. المهمة جاهزة للمراجعة.');
    }

    public function submit(Request $request, Project $project, string $exercise): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $definition = $this->definition($exercise);
        $run = MarketingLearningRun::startFor($project, $request->user());
        $attempt = $run->attemptFor($exercise);

        if (in_array($attempt->status, [
            MarketingExerciseAttempt::STATUS_QUEUED,
            MarketingExerciseAttempt::STATUS_EVALUATING,
            MarketingExerciseAttempt::STATUS_COMPLETED,
        ], true)) {
            return redirect()->route('app.learning.marketing.result', [$project, $exercise]);
        }

        $completeness = $this->completeness->score($definition, $attempt->answers ?? []);

        if ($completeness['missing'] !== []) {
            $missingKey = $completeness['missing'][0];
            $step = collect($definition['questions'])->search(fn (array $question) => $question['key'] === $missingKey);

            return redirect()->route('app.learning.marketing.exercise', [
                $project,
                $exercise,
                'step' => ((int) $step) + 1,
            ])->with('error', 'بقيت إجابة واحدة أو أكثر. أكملها ثم نراجع المهمة كاملة.');
        }

        $answersAtSubmission = $attempt->answers;
        $queued = DB::transaction(function () use ($attempt, $completeness, $answersAtSubmission): bool {
            $current = MarketingExerciseAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($current->answers !== $answersAtSubmission || in_array($current->status, [
                MarketingExerciseAttempt::STATUS_QUEUED,
                MarketingExerciseAttempt::STATUS_EVALUATING,
                MarketingExerciseAttempt::STATUS_COMPLETED,
            ], true)) {
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

        if (! $queued) {
            return redirect()->route('app.learning.marketing.result', [$project, $exercise]);
        }

        $attempt->refresh();

        $dispatched = $this->dispatchReview($attempt);

        return redirect()->route('app.learning.marketing.result', [$project, $exercise])
            ->with('status', $dispatched
                ? 'استلمنا إجاباتك. نراجعها الآن ونجهز لك المقترحات.'
                : 'حفظنا إجاباتك، لكن المراجعة لم تبدأ. يمكنك إعادة المحاولة من صفحة النتيجة.');
    }

    public function result(Request $request, Project $project, string $exercise): View
    {
        $this->authorizeProject($request, $project);
        $definition = $this->definition($exercise);
        $run = MarketingLearningRun::startFor($project, $request->user());
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

    public function retry(Request $request, Project $project, string $exercise): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->definition($exercise);
        $run = MarketingLearningRun::startFor($project, $request->user());
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
            $dispatched = $this->dispatchReview($retryAttempt);

            return redirect()->route('app.learning.marketing.result', [$project, $exercise])
                ->with('status', $dispatched
                    ? 'بدأنا المراجعة مرة أخرى، وإجاباتك محفوظة كما هي.'
                    : 'إجاباتك محفوظة، لكن المراجعة لم تبدأ. حاول مرة أخرى بعد قليل.');
        }

        return redirect()->route('app.learning.marketing.result', [$project, $exercise])
            ->with('status', 'بدأنا المراجعة مرة أخرى، وإجاباتك محفوظة كما هي.');
    }

    private function dispatchReview(MarketingExerciseAttempt $attempt): bool
    {
        try {
            // تفريغ PendingDispatch داخل try يجعل خطأ الاتصال بالطابور قابلًا
            // للمعالجة هنا بدل ترك المحاولة عالقة في حالة الانتظار.
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

            Log::warning('تعذر إرسال مهمة تعلم التسويق إلى المراجعة.', [
                'attempt_id' => $attempt->id,
                'exercise_key' => $attempt->exercise_key,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
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
}
