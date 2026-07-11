<?php

namespace App\Http\Controllers\Web;

use App\Application\Execution\AdvanceExecutionPackageStatusAction;
use App\Application\Execution\CreateExecutionReportAction;
use App\Application\Execution\UpdateExecutionTaskStatusAction;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\ExecutionReport;
use App\Domain\Execution\Models\ExecutionTask;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Agency\WhiteLabelResolver;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutionPackageController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function show(Request $request, ExecutionPackage $executionPackage, WhiteLabelResolver $whiteLabel): View
    {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);

        $executionPackage->load(['tasks.assignee', 'assets', 'reports', 'recommendation', 'project']);
        $this->authorize('view', $executionPackage->project);

        return view('app.execution-packages.show', [
            'package' => $executionPackage,
            'brand' => $whiteLabel->for($workspace),
        ]);
    }

    public function updateStatus(
        Request $request,
        ExecutionPackage $executionPackage,
        FlashMessageCatalog $flash,
        AdvanceExecutionPackageStatusAction $advanceExecutionPackageStatus,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionPackage::STATUSES)],
        ]);

        $advanceExecutionPackageStatus->handle($executionPackage, $validated['status']);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->statusUpdated('حزمة التنفيذ'));
    }

    public function updateTaskStatus(
        Request $request,
        ExecutionPackage $executionPackage,
        ExecutionTask $executionTask,
        FlashMessageCatalog $flash,
        UpdateExecutionTaskStatusAction $updateExecutionTaskStatus,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        abort_unless($executionTask->execution_package_id === $executionPackage->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionTask::STATUSES)],
        ]);

        $updateExecutionTaskStatus->handle($executionTask, $validated['status']);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->statusUpdated('مهمة التنفيذ'));
    }

    public function storeReport(
        Request $request,
        ExecutionPackage $executionPackage,
        FlashMessageCatalog $flash,
        CreateExecutionReportAction $createExecutionReport,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'phase' => ['required', 'string', 'in:'.implode(',', ExecutionReport::PHASES)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'metric_name' => ['nullable', 'string', 'max:120'],
            'metric_value' => ['nullable', 'string', 'max:120'],
        ]);

        $notes = filled($validated['note'] ?? null) ? ['summary' => $validated['note']] : [];
        $metrics = filled($validated['metric_name'] ?? null) || filled($validated['metric_value'] ?? null)
            ? [[
                'name' => $validated['metric_name'] ?? '',
                'value' => $validated['metric_value'] ?? '',
            ]]
            : [];

        $createExecutionReport->handle($executionPackage, [
            'phase' => $validated['phase'],
            'progress' => $validated['progress'],
            'notes_json' => $notes,
            'metrics_json' => $metrics,
        ]);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->created('تقرير القياس'));
    }
}
