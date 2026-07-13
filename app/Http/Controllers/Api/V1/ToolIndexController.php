<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Resources\V1\ToolResource;
use App\Domain\Entitlement\Services\EntitlementResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ToolIndexController
{
    /**
     * فهرس الأدوات المتاحة (المنشورة/بيتا) مرتّبة حسب المرحلة.
     */
    public function index(Request $request, EntitlementResolver $resolver): AnonymousResourceCollection
    {
        /** @var Workspace $workspace */
        $workspace = app('currentWorkspace');

        $project = $request->filled('project_public_id')
            ? Project::query()
                ->where('workspace_id', $workspace->id)
                ->where('public_id', $request->string('project_public_id')->value())
                ->first()
            : null;

        $tools = Tool::query()
            ->whereIn('status', ['published', 'beta'])
            ->orderBy('stage')
            ->orderBy('sort_order')
            ->get();

        if ($project) {
            $projectRunCounts = ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $project->id)
                ->selectRaw('tool_code, COUNT(*) as aggregate')
                ->groupBy('tool_code')
                ->pluck('aggregate', 'tool_code');
            $completedToolCodes = $projectRunCounts->keys()->all();

            $tools = $tools
                ->map(function (Tool $tool) use ($resolver, $workspace, $project, $projectRunCounts, $completedToolCodes): Tool {
                    $tool->unlocked = $resolver->boolean('modules.stage_'.$tool->stage, $workspace, (int) $tool->stage === 1);
                    $tool->completed_in_current_project = in_array($tool->code, $completedToolCodes, true);
                    $tool->current_project_runs = (int) ($projectRunCounts[$tool->code] ?? 0);
                    $tool->recommended_now = $tool->unlocked
                        && ! $tool->completed_in_current_project
                        && (int) $tool->stage === (int) $project->stage;

                    return $tool;
                })
                ->sortBy([
                    ['recommended_now', 'desc'],
                    ['completed_in_current_project', 'asc'],
                    ['stage', 'asc'],
                    ['sort_order', 'asc'],
                ])
                ->values();
        }

        return ToolResource::collection($tools);
    }
}
