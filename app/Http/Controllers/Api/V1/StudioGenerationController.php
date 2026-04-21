<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\AI\GenerateTemplateDraftAction;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\GenerateStudioOutputRequest;
use Illuminate\Http\JsonResponse;

class StudioGenerationController extends Controller
{
    public function store(
        GenerateStudioOutputRequest $request,
        GenerateTemplateDraftAction $action,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('useTools', $workspace);

        $template = AITemplate::query()->findOrFail($request->validated('template_id'));
        $projectId = $request->validated('project_id');
        $project = $projectId
            ? Project::query()->where('workspace_id', $workspace->id)->findOrFail($projectId)
            : null;

        $generation = $action->handle(
            workspace: $workspace,
            template: $template,
            project: $project?->load('client'),
            actor: $request->user(),
            brief: $request->validated('brief'),
        );

        return response()->json([
            'data' => [
                'public_id' => $generation->public_id,
                'status' => $generation->status,
                'template_id' => $generation->template_id,
                'project_id' => $generation->project_id,
                'created_at' => $generation->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
