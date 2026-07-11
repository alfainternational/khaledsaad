<?php

namespace App\Http\Controllers\Web;

use App\Domain\Execution\Models\ExecutionPackage;
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

        $executionPackage->load(['tasks', 'assets', 'recommendation', 'project']);
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
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionPackage::STATUSES)],
        ]);

        $executionPackage->update(['status' => $validated['status']]);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->statusUpdated('حزمة التنفيذ'));
    }

    public function updateTaskStatus(
        Request $request,
        ExecutionPackage $executionPackage,
        ExecutionTask $executionTask,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        abort_unless($executionTask->execution_package_id === $executionPackage->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionTask::STATUSES)],
        ]);

        $executionTask->update(['status' => $validated['status']]);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->statusUpdated('مهمة التنفيذ'));
    }
}
