<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolRunFile;
use App\Services\Tools\AttachmentUploader;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ToolRunController extends Controller
{
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
        $total = count($wizard['steps']);

        if ($step < 1 || $step > $total) {
            return redirect()->route('app.runs.step', [$run, min(max(1, $step), max(1, $total))]);
        }

        return view('app.runs.step', [
            'run' => $wizard,
            'step' => $wizard['steps'][$step - 1],
            'step_number' => $step,
            'total_steps' => $total,
        ]);
    }

    public function saveStep(Request $request, ToolRun $run, int $step): RedirectResponse
    {
        $this->authorizeRun($request, $run);

        $this->service->saveStep($run, $step, $request->except(['_token', '_method']));

        $total = count($this->presenter->wizard($run)['steps']);

        return $step >= $total
            ? redirect()->route('app.runs.review', $run)
            : redirect()->route('app.runs.step', [$run, $step + 1]);
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
        $this->service->queue($run);

        return redirect()->route('app.runs.status', $run);
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
