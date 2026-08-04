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
use Illuminate\View\View;
use InvalidArgumentException;

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
        $answers = $attempt->answers ?? [];
        $changed = ($answers[$question['key']] ?? null) !== $data['answer'];
        $answers[$question['key']] = $data['answer'];

        $attempt->forceFill([
            'answers' => $answers,
            'status' => $changed ? MarketingExerciseAttempt::STATUS_DRAFT : $attempt->status,
        ])->save();

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

        $attempt->forceFill([
            'status' => MarketingExerciseAttempt::STATUS_QUEUED,
            'completeness_score' => $completeness['score'],
            'failure_reason' => null,
            'submitted_at' => now(),
        ])->save();

        EvaluateMarketingExercise::dispatch($attempt->id);

        return redirect()->route('app.learning.marketing.result', [$project, $exercise])
            ->with('status', 'استلمنا إجاباتك. نراجعها الآن ونجهز لك المقترحات.');
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
        ]);
    }

    public function retry(Request $request, Project $project, string $exercise): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->definition($exercise);
        $run = MarketingLearningRun::startFor($project, $request->user());
        $attempt = $run->attempts()->where('exercise_key', $exercise)->firstOrFail();

        if ($attempt->status === MarketingExerciseAttempt::STATUS_REVIEW_FAILED) {
            $attempt->forceFill([
                'status' => MarketingExerciseAttempt::STATUS_QUEUED,
                'failure_reason' => null,
                'submitted_at' => now(),
            ])->save();
            EvaluateMarketingExercise::dispatch($attempt->id);
        }

        return redirect()->route('app.learning.marketing.result', [$project, $exercise])
            ->with('status', 'بدأنا المراجعة مرة أخرى، وإجاباتك محفوظة كما هي.');
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
