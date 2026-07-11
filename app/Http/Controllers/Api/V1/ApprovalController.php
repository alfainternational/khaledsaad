<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Approval\SyncExecutionPackageApprovalStateAction;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\Approval\Models\Approval;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Tool\Models\ToolRun;
use App\Http\Api\ApiException;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RequestApprovalRequest;
use App\Http\Requests\Web\ReviewApprovalRequest;
use App\Http\Resources\V1\ApprovalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    use ResolvesCurrentProject;

    /**
     * موافقات مساحة العمل مع عدّادات الحالة (نفس بيانات الويب).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
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
            ->paginate(15);

        return response()->json([
            'data' => ApprovalResource::collection($approvals->items())->resolve($request),
            'meta' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'total' => $approvals->total(),
                'pending_count' => (int) ($statusCounts['pending'] ?? 0),
                'approved_count' => (int) ($statusCounts['approved'] ?? 0),
                'rejected_count' => (int) ($statusCounts['rejected'] ?? 0),
            ],
        ]);
    }

    /**
     * طلب موافقة على عنصر داخل مشروع. يدعم item_public_id من الموبايل.
     */
    public function store(
        RequestApprovalRequest $request,
        SyncExecutionPackageApprovalStateAction $syncExecutionPackageApprovalState,
    ): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $project = $this->currentProject();
        $this->authorize('requestApprovals', $workspace);
        $this->authorize('view', $project);

        $data = $request->validated();
        $itemType = $data['item_type'];
        $itemId = (int) $data['item_id'];

        if (! $this->itemBelongsToProject($workspace->id, $project->id, $itemType, $itemId)) {
            throw new ApiException('العنصر لا يتبع هذا المشروع.', 'ITEM_MISMATCH', 422);
        }

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

        return (new ApprovalResource($approval->load(['project.client', 'reviewer', 'toolRun.tool', 'aiGeneration.template', 'executionPackage'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        ReviewApprovalRequest $request,
        SyncExecutionPackageApprovalStateAction $syncExecutionPackageApprovalState,
    ): ApprovalResource
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $approval = Approval::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((int) $request->route('approvalId'));

        $this->authorize('review', $approval);

        $data = $request->validated();

        $approval->update([
            'status' => $request->validated('status'),
            'note' => array_key_exists('note', $data) ? $data['note'] : $approval->note,
            'reviewer_id' => $request->user()->id,
        ]);

        $syncExecutionPackageApprovalState->applyDecision($approval);

        return new ApprovalResource($approval->load(['project.client', 'reviewer', 'toolRun.tool', 'aiGeneration.template', 'executionPackage']));
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
