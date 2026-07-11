<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Intelligence\Models\AuditRun;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Jobs\RunProjectIntelligenceAuditJob;
use App\Support\Intelligence\MarketingIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAuditController extends Controller
{
    use ResolvesCurrentProject;

    /**
     * جدولة تحليل Marketing Intelligence غير متزامن للمشروع.
     */
    public function store(
        Request $request,
        MarketingIntelligenceService $intelligence,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $project = $this->currentProject();
        $this->authorize('update', $project);

        $activeRun = $intelligence->activeRun($project);
        if ($activeRun) {
            return response()->json([
                'data' => [
                    'queued' => false,
                    'status' => $activeRun->status,
                    'message' => 'يوجد تحليل قيد التنفيذ بالفعل لهذا المشروع.',
                ],
            ]);
        }

        $auditRun = $intelligence->queue($project->fresh(), $workspace, 'manual');
        RunProjectIntelligenceAuditJob::dispatch($auditRun->id);

        return response()->json([
            'data' => [
                'queued' => true,
                'status' => $auditRun->status,
                'message' => 'تمت جدولة التحليل وسيكتمل تلقائياً.',
            ],
        ], 202);
    }

    /**
     * حالة آخر تدقيق — يستعملها التطبيق للـ polling.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $project = $this->currentProject();
        $this->authorize('view', $project);

        $latest = AuditRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->latest()
            ->first();

        return response()->json([
            'data' => [
                'status' => $latest?->status,
                'in_progress' => in_array($latest?->status, ['queued', 'running'], true),
                'summary' => $latest?->summary_json,
                'completed_at' => $latest?->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
