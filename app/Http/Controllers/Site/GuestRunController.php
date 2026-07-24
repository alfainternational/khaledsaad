<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\NavigatesWizardSteps;
use App\Http\Controllers\Controller;
use App\Models\GuestSession;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Services\Guests\GuestSessionManager;
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
        ]);
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
