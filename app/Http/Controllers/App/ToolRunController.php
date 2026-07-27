<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\NavigatesWizardSteps;
use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolRunFile;
use App\Services\Tools\AttachmentUploader;
use App\Services\Tools\HybridInsightService;
use App\Services\Tools\ManualReportService;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ToolRunController extends Controller
{
    use NavigatesWizardSteps;
    use ResolvesWorkspace;

    public function __construct(
        private readonly ToolRunService $service,
        private readonly RunPresenter $presenter,
        private readonly AttachmentUploader $uploader,
    ) {}

    public function start(Request $request, Project $project, Tool $tool): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        try {
            $run = $this->service->start(
                $project,
                $tool,
                $request->user(),
                fresh: $request->boolean('fresh'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['tool' => $exception->getMessage()]);
        }

        // المستأنف يعود إلى حيث وقف، والمبتدئ يبدأ من الخطوة الأولى.
        return redirect()->route('app.runs.step', [$run, max(1, min($run->current_step, $run->toolVersion->stepCount()))]);
    }

    public function step(Request $request, ToolRun $run, int $step): View|RedirectResponse
    {
        $this->authorizeRun($request, $run);

        if ($run->status !== ToolRun::STATUS_DRAFT) {
            return redirect()->route('app.runs.status', $run);
        }

        $wizard = $this->presenter->wizard($run);
        $steps = $wizard['steps'];
        $current = $this->currentStep($steps, $step);

        // الخطوة المطلوبة لا تخص هذا المشروع (أو لم تعد قائمة): ننقله لأقربها.
        if ($current === null) {
            $nearest = $this->nearestStep($steps, $step);

            return $nearest === null
                ? redirect()->route('app.runs.review', $run)
                : redirect()->route('app.runs.step', [$run, $nearest]);
        }

        return view('app.runs.step', [
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
        $this->authorizeRun($request, $run);

        $this->service->saveStep($run, $step, $request->except(['_token', '_method']));

        // الخطوات تُعاد قراءتها بعد الحفظ: إجابة هذه الخطوة قد تكشف خطوة تالية.
        $next = $this->stepAfter($this->presenter->wizard($run)['steps'], $step);

        return $next === null
            ? redirect()->route('app.runs.review', $run)
            : redirect()->route('app.runs.step', [$run, $next]);
    }

    public function insights(
        Request $request,
        ToolRun $run,
        HybridInsightService $insights,
    ): JsonResponse {
        $this->authorizeRun($request, $run);
        $validated = $request->validate([
            'answers' => 'nullable|array|max:50',
            'include_ai' => 'nullable|boolean',
            'step' => 'nullable|integer|min:1|max:50',
        ]);

        return response()->json(['data' => $insights->preview(
            $run,
            $validated['answers'] ?? [],
            (bool) ($validated['include_ai'] ?? false),
            isset($validated['step']) ? (int) $validated['step'] : null,
        )]);
    }

    public function review(Request $request, ToolRun $run): View
    {
        $this->authorizeRun($request, $run);

        return view('app.runs.review', [
            'run' => $this->presenter->wizard($run),
            'preflight' => $this->service->preflight($run),
        ]);
    }

    public function queue(Request $request, ToolRun $run): RedirectResponse
    {
        $this->authorizeRun($request, $run);

        try {
            $this->service->queue($run);
        } catch (RuntimeException $exception) {
            // رصيد غير كافٍ أو حصة خطة منتهية: رسالة واضحة في مكانها،
            // لا صفحة خطأ تقني تُخرج المستخدم من المسار.
            return back()->withErrors(['queue' => $exception->getMessage()]);
        }

        return redirect()->route('app.runs.status', $run);
    }

    /**
     * المسار اليدوي: يجمّد التشغيل بانتظار مراجعة الآدمن بدل خط الأنابيب.
     */
    public function requestManualReview(Request $request, ToolRun $run, ManualReportService $manual): RedirectResponse
    {
        $this->authorizeRun($request, $run);
        $manual->requestManualReview($run);

        return redirect()->route('app.runs.status', $run)
            ->with('status', 'وصلتنا إجاباتك. سيراجعها خالد بنفسه، ونُشعرك فور جاهزية النتيجة.');
    }

    public function status(Request $request, ToolRun $run): View|RedirectResponse
    {
        $this->authorizeRun($request, $run);

        if ($run->report !== null && $run->isTerminal()) {
            return redirect()->route('app.reports.show', $run->report);
        }

        return view('app.runs.status', ['run' => $this->presenter->progress($run)]);
    }

    /**
     * نقطة الاستطلاع: الويب والتطبيق يستهلكان نفس الحمولة بالضبط.
     */
    public function progress(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->presenter->progress($run)]);
    }

    public function retry(Request $request, ToolRun $run): RedirectResponse
    {
        $this->authorizeRun($request, $run);
        $this->service->retry($run);

        return redirect()->route('app.runs.status', $run);
    }

    public function uploadFile(Request $request, ToolRun $run): RedirectResponse
    {
        $this->authorizeRun($request, $run);

        $request->validate(['file' => AttachmentUploader::validationRules()]);

        $this->uploader->store($run, $request->file('file'));

        return back()->with('status', 'أُرفق الملف. سنقرأه عند التحليل.');
    }

    public function deleteFile(Request $request, ToolRun $run, ToolRunFile $file): RedirectResponse
    {
        $this->authorizeRun($request, $run);

        abort_unless($file->tool_run_id === $run->id, 404);

        $this->uploader->delete($file);

        return back()->with('status', 'حُذف الملف.');
    }
}
