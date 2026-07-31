<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\NavigatesWizardSteps;
use App\Http\Controllers\Controller;
use App\Models\GuestSession;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Modules\Diagnosis\GuestPreview;
use App\Services\Guests\GuestSessionManager;
use App\Services\Tools\AnswerCompleteness;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * التجربة قبل الحساب: يجرّب الزائر أداة كاملة ويرى نتيجته،
 * ثم يسجّل ليحفظها — لا العكس.
 */
class GuestRunController extends Controller
{
    use NavigatesWizardSteps;

    public function __construct(
        private readonly GuestSessionManager $guests,
        private readonly ToolRunService $service,
        private readonly RunPresenter $presenter,
        private readonly GuestPreview $preview,
    ) {}

    /**
     * بدء تجربة: جلسة زائر + مشروع مؤقت + أول خطوة.
     */
    public function start(Request $request, Tool $tool): RedirectResponse
    {
        if (! $tool->isRunnable()) {
            return redirect()->route('tools.show', $tool->key);
        }

        $session = $this->guests->start($request);
        $project = $this->guests->project($session);

        try {
            $run = $this->service->start($project, $tool, null, $session->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['tool' => $exception->getMessage()]);
        }

        return redirect()->route('try.step', [$run, 1]);
    }

    public function step(Request $request, ToolRun $run, int $step): View|RedirectResponse
    {
        $this->authorizeGuestRun($request, $run);

        if ($run->status !== ToolRun::STATUS_DRAFT) {
            return redirect()->route('try.result', $run);
        }

        $wizard = $this->presenter->wizard($run);
        $steps = $wizard['steps'];
        $current = $this->currentStep($steps, $step);

        if ($current === null) {
            $nearest = $this->nearestStep($steps, $step);

            return $nearest === null
                ? redirect()->route('try.result', $run)
                : redirect()->route('try.step', [$run, $nearest]);
        }

        return view('site.try.step', [
            'run' => $wizard,
            'step' => $current,
            'step_number' => $current['step'],
            'position' => $current['position'],
            'total_steps' => count($steps),
            'previous_step' => $this->stepBefore($steps, $step),
            'next_step' => $this->stepAfter($steps, $step),
        ]);
    }

    public function saveStep(Request $request, ToolRun $run, int $step): RedirectResponse
    {
        $this->authorizeGuestRun($request, $run);

        $this->service->saveStep($run, $step, $request->except(['_token', '_method']));

        $next = $this->stepAfter($this->presenter->wizard($run)['steps'], $step);

        if ($next !== null) {
            return redirect()->route('try.step', [$run, $next]);
        }

        // اكتملت الأسئلة: هنا يرى قيمة ما فعله، ثم نعرض عليه حفظه.
        return redirect()->route('try.result', $run);
    }

    public function result(Request $request, ToolRun $run): View
    {
        $this->authorizeGuestRun($request, $run);

        $preflight = $this->service->preflight($run);

        return view('site.try.result', [
            'run' => $this->presenter->wizard($run),
            'preflight' => $preflight,
            'tool' => $run->toolVersion->tool,

            /*
             * المستوى ٠ يعرض الدرجة والفجوات بالاسم فقط. قبل هذا كان الزائر
             * يخرج بمراجعة لما كتبه هو — وهذا لا يخلق فجوة معرفية ولا يبررها،
             * فيصير طلب التسجيل بلا مقابل معروف (§٦).
             */
            'preview' => $preflight['missing'] === []
                ? $this->preview->build($run->toolVersion, $this->answersOf($run), $this->activeKeysOf($run))
                : null,

            /*
             * مقارنة الأقران: متوسط من أكملوا التشخيص نفسه. تُعرض فقط حين
             * تبلغ العينة ١٠ فأكثر (§٤.٢ — لا قياس من عينة صغيرة)، ومعها
             * أساسها دائمًا (§١٣).
             */
            'peers' => $this->peersOf($run),
        ]);
    }

    /**
     * @return array{average: int, count: int}|null
     */
    private function peersOf(ToolRun $run): ?array
    {
        $stats = ToolRun::where('tool_version_id', $run->tool_version_id)
            ->where('id', '!=', $run->id)
            ->whereNotNull('base_score')
            ->selectRaw('count(*) as n, avg(base_score) as avg_score')
            ->first();

        if ($stats === null || (int) $stats->n < 10) {
            return null;
        }

        return ['average' => (int) round((float) $stats->avg_score), 'count' => (int) $stats->n];
    }

    /**
     * @return array<string, mixed>
     */
    private function answersOf(ToolRun $run): array
    {
        return collect($run->answerMap())
            ->map(fn ($value) => is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value)
            ->all();
    }

    /**
     * الأسئلة المنطبقة على هذا المشروع وحدها — عدالة التكيف نفسها التي يطبّقها
     * خط الأنابيب. مشروع فكرة لا يُعاقَب في درجته على سؤال قنوات لم يُعرض له.
     *
     * @return array<int, string>
     */
    private function activeKeysOf(ToolRun $run): array
    {
        $completeness = app(AnswerCompleteness::class);

        return $completeness
            ->visibleFields($run->toolVersion, $completeness->contextualAnswers($run))
            ->pluck('key')
            ->all();
    }

    /**
     * الزائر يملك تشغيله عبر ملف تعريف الارتباط وحده — لا حساب ولا رابط سري في العنوان.
     */
    private function authorizeGuestRun(Request $request, ToolRun $run): void
    {
        $session = $this->guests->current($request);

        if (! $session instanceof GuestSession || $run->guest_session_id !== $session->id) {
            throw new NotFoundHttpException;
        }
    }
}
