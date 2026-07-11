<?php

namespace App\Http\Controllers\Web;

use App\Application\Approval\SyncExecutionPackageApprovalStateAction;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\Approval\Models\Approval;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\RequestApprovalRequest;
use App\Http\Requests\Web\ReviewApprovalRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function index(Request $request): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('viewApprovals', $workspace);
        $statusCounts = Approval::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $approvals = Approval::query()
            ->where('workspace_id', $workspace->id)
            ->with(['project.client', 'reviewer', 'toolRun.tool', 'aiGeneration.template', 'executionPackage'])
            ->when(
                $request->string('status')->isNotEmpty(),
                fn ($query) => $query->where('status', $request->string('status')->value())
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('app.approvals.index', [
            'workspace' => $workspace,
            'approvals' => $approvals,
            'pendingCount' => (int) ($statusCounts['pending'] ?? 0),
            'approvedCount' => (int) ($statusCounts['approved'] ?? 0),
            'rejectedCount' => (int) ($statusCounts['rejected'] ?? 0),
        ]);
    }

    public function store(
        RequestApprovalRequest $request,
        Project $project,
        FlashMessageCatalog $flash,
        SyncExecutionPackageApprovalStateAction $syncExecutionPackageApprovalState,
    ): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('requestApprovals', $workspace);
        $this->authorize('view', $project);
        abort_unless($project->workspace_id === $workspace->id, 404);

        $data = $request->validated();
        $itemType = $data['item_type'];
        $itemId = (int) $data['item_id'];

        abort_unless($this->itemBelongsToProject($workspace->id, $project->id, $itemType, $itemId), 422);

        $approval = Approval::query()->updateOrCreate([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'status' => 'pending',
        ], [
            'reviewer_id' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ]);

        $syncExecutionPackageApprovalState->markRequested($approval);

        return back()->with('status', $flash->approvalSubmitted());
    }

    public function update(
        ReviewApprovalRequest $request,
        Approval $approval,
        FlashMessageCatalog $flash,
        SyncExecutionPackageApprovalStateAction $syncExecutionPackageApprovalState,
    ): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('review', $approval);
        abort_unless($approval->workspace_id === $workspace->id, 404);

        $data = $request->validated();

        $approval->update([
            'status' => $request->validated('status'),
            'note' => array_key_exists('note', $data) ? $data['note'] : $approval->note,
            'reviewer_id' => $request->user()->id,
        ]);

        $syncExecutionPackageApprovalState->applyDecision($approval);

        return back()->with('status', $flash->approvalUpdated());
    }

    private function itemBelongsToProject(int $workspaceId, int $projectId, string $itemType, int $itemId): bool
    {
        return match ($itemType) {
            'tool_run' => ToolRun::query()
                ->where('workspace_id', $workspaceId)
                ->where('project_id', $projectId)
                ->whereKey($itemId)
                ->exists(),
            'ai_generation' => AIGeneration::query()
                ->where('workspace_id', $workspaceId)
                ->where('project_id', $projectId)
                ->whereKey($itemId)
                ->exists(),
            'execution_package' => ExecutionPackage::query()
                ->where('workspace_id', $workspaceId)
                ->where('project_id', $projectId)
                ->whereKey($itemId)
                ->exists(),
            default => false,
        };
    }
}
