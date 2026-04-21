<?php

namespace App\Http\Controllers\Api;

use App\Application\Tooling\RunToolAction;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\ExecuteToolRequest;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Tooling\ToolFormExperienceBuilder;
use App\Support\Tooling\ToolModePolicy;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToolRunApiController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function store(
        ExecuteToolRequest $request,
        Tool $tool,
        RunToolAction $action,
        ToolModePolicy $toolModePolicy,
        WorkspaceProfileStore $profileStore,
    ): JsonResponse {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);
        abort_unless($tool->status !== 'hidden', 404);

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($request->validated('project_id'));

        $this->authorize('view', $project);
        $profile = $profileStore->get($workspace);
        $requestedMode = (string) $request->validated('mode');

        $runs = ToolRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->where('tool_code', $tool->code)
            ->latest()
            ->get();

        $resolvedMode = $toolModePolicy->resolveMode(
            $tool,
            $requestedMode,
            $runs->first(),
            $runs->count(),
            $profile['awareness_level'] ?? null,
        );

        if ($resolvedMode === null) {
            return response()->json([
                'success' => false,
                'error' => 'الوضع المطلوب غير متاح حالياً. أكمل الخطوات السابقة أولاً.',
            ], 422);
        }

        $run = $action->handle(
            $workspace,
            $project->load('client'),
            $tool,
            $request->user(),
            $resolvedMode,
            array_filter([
                'brief' => $request->validated('brief'),
                ...($request->validated('inputs') ?? []),
            ], fn ($value) => $value !== null && $value !== '')
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ النتيجة بنجاح.',
            'data' => [
                'run_id' => $run->id,
                'run_public_id' => $run->public_id,
                'completeness_score' => $run->completeness_score,
                'summary' => $run->summary_json,
                'ai_generated' => (bool) ($run->summary_json['ai_generated'] ?? false),
                'next_actions' => $run->next_actions_json,
                'output' => $run->output_json,
                'inputs' => $run->inputs_json,
                'mode' => $run->mode,
                'created_at' => $run->created_at?->diffForHumans(),
            ],
        ]);
    }

    public function load(
        Request $request,
        Tool $tool,
        ToolBlueprintCatalog $toolBlueprintCatalog,
        ToolFormExperienceBuilder $toolFormExperienceBuilder,
        WorkspaceProfileStore $profileStore,
    ): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);
        $projectId = $request->input('project_id') ?? $request->query('project_id');

        if (! $projectId) {
            return response()->json(['success' => false, 'error' => 'project_id مطلوب.'], 422);
        }

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->find($projectId);

        if (! $project) {
            return response()->json(['success' => false, 'data' => null]);
        }

        $profile = $profileStore->get($workspace);
        $blueprint = $toolBlueprintCatalog->for($tool);

        $latestRun = ToolRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->where('tool_code', $tool->code)
            ->latest()
            ->first();

        $formExperience = $toolFormExperienceBuilder->build(
            $tool,
            $blueprint,
            $profile,
            $project->loadMissing('client'),
            $latestRun,
        );

        if (! $latestRun) {
            return response()->json([
                'success' => true,
                'data' => null,
                'experience' => $formExperience,
            ]);
        }

        return response()->json([
            'success' => true,
            'experience' => $formExperience,
            'data' => [
                'run_id' => $latestRun->id,
                'run_public_id' => $latestRun->public_id,
                'completeness_score' => $latestRun->completeness_score,
                'summary' => $latestRun->summary_json,
                'ai_generated' => (bool) ($latestRun->summary_json['ai_generated'] ?? false),
                'next_actions' => $latestRun->next_actions_json,
                'output' => $latestRun->output_json,
                'inputs' => $latestRun->inputs_json,
                'mode' => $latestRun->mode,
                'created_at' => $latestRun->created_at?->diffForHumans(),
            ],
        ]);
    }
}
