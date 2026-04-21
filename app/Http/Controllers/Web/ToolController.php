<?php

namespace App\Http\Controllers\Web;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Dashboard\StageCatalog;
use App\Support\Dashboard\ToolExperienceResolver;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Tooling\ToolCopyCatalog;
use App\Support\Tooling\ToolFormExperienceBuilder;
use App\Support\Tooling\ToolModePolicy;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToolController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function show(
        Request $request,
        Tool $tool,
        ToolExperienceResolver $toolExperienceResolver,
        WorkspaceProfileStore $profileStore,
        ToolBlueprintCatalog $toolBlueprintCatalog,
        ToolFormExperienceBuilder $toolFormExperienceBuilder,
        ToolModePolicy $toolModePolicy,
        ToolCopyCatalog $toolCopyCatalog,
    ): View {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);
        abort_unless($tool->status !== 'hidden', 404);
        $profile = $profileStore->get($workspace);
        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get();
        $currentProject = $projects->firstWhere('status', 'active') ?? $projects->first();
        $latestRun = $currentProject
            ? ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $currentProject->id)
                ->where('tool_code', $tool->code)
                ->with(['project.client', 'author'])
                ->latest()
                ->first()
            : null;
        $projectModeRuns = $currentProject
            ? ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $currentProject->id)
                ->where('tool_code', $tool->code)
                ->latest()
                ->get()
            : collect();
        $projectLatestRun = $projectModeRuns->first();
        $projectRunCount = $projectModeRuns->count();
        $modeAvailability = $toolModePolicy->availability(
            $tool,
            $projectLatestRun,
            $projectRunCount,
            $profile['awareness_level'] ?? null,
        );
        $experience = $toolExperienceResolver->resolve($tool, $profile);
        $experienceRecommendedMode = (string) ($experience['recommended_mode'] ?? '');
        $recommendedMode = ($modeAvailability[$experienceRecommendedMode]['available'] ?? false)
            ? $experienceRecommendedMode
            : (array_key_first(
            array_filter(
                $modeAvailability,
                fn (array $modeState): bool => $modeState['available'] === true
            )
        ) ?? $toolModePolicy->fallbackMode($tool));

        $blueprint = $toolBlueprintCatalog->for($tool);
        $upstreamContext = $this->buildUpstreamContext($workspace->id, $currentProject?->id, $tool);
        $formExperience = $toolFormExperienceBuilder->build(
            $tool,
            $blueprint,
            $profile,
            $currentProject?->loadMissing('client'),
            $latestRun,
            $upstreamContext,
        );

        $feedsInto = collect($tool->feeds_into_json ?? [])
            ->map(fn (string $code) => Tool::query()->where('code', $code)->where('status', '!=', 'hidden')->first())
            ->filter()
            ->values();

        return view('app.tools.show', [
            'workspace' => $workspace,
            'tool' => $tool,
            'profile' => $profile,
            'projects' => $projects,
            'currentProject' => $currentProject,
            'latestRun' => $latestRun,
            'stageLabel' => StageCatalog::label((int) $tool->stage),
            'experience' => $experience,
            'blueprint' => $blueprint,
            'formExperience' => $formExperience,
            'modeAvailability' => $modeAvailability,
            'recommendedMode' => $recommendedMode,
            'projectModeContext' => [
                'run_count' => $projectRunCount,
                'latest_completeness' => (int) ($projectLatestRun?->completeness_score ?? 0),
            ],
            'uiCopy' => [
                'submit_label' => $toolCopyCatalog->submitLabel(),
            ],
            'upstreamContext' => $upstreamContext,
            'feedsInto' => $feedsInto,
        ]);
    }

    /**
     * @return array<int, array{tool_code: string, tool_name: string, headline: string, text: string, completeness: int}>
     */
    private function buildUpstreamContext(int $workspaceId, ?int $projectId, Tool $tool): array
    {
        if (! $projectId) {
            return [];
        }

        $dependsOn = $tool->depends_on_json ?? [];

        if (empty($dependsOn)) {
            return [];
        }

        $keys = collect($dependsOn)->map(fn (string $code) => 'tool.summary.'.$code)->all();

        return WorkspaceData::query()
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $projectId)
            ->whereIn('key', $keys)
            ->get()
            ->map(fn (WorkspaceData $row) => [
                'tool_code' => str_replace('tool.summary.', '', $row->key),
                'tool_name' => $row->value_json['stage_label'] ?? str_replace('tool.summary.', '', $row->key),
                'headline' => $row->value_json['headline'] ?? '',
                'text' => $row->value_json['text'] ?? '',
                'completeness' => (int) ($row->value_json['completeness_score'] ?? 0),
            ])
            ->filter(fn (array $item) => $item['headline'] !== '')
            ->values()
            ->all();
    }
}
