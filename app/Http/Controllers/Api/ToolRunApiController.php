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
use App\Support\Tooling\ToolStrategicAdvisor;
use App\Support\Projects\ProjectMarketingBriefStore;
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
        ProjectMarketingBriefStore $briefStore,
        ToolStrategicAdvisor $toolStrategicAdvisor,
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
        $upstreamContext = $this->buildUpstreamContext($workspace->id, $project->id, $tool);
        $projectBrief = $briefStore->get($workspace, $project);
        $projectBriefAssessment = $briefStore->assess($projectBrief);
        $toolBriefing = $toolStrategicAdvisor->advise(
            $tool,
            $profile,
            $project,
            $projectBrief,
            $projectBriefAssessment,
            $upstreamContext,
        );
        $toolBriefing = $this->decorateToolBriefing($toolBriefing, $project);

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
            $upstreamContext,
            $toolBriefing,
        );

        if (! $latestRun) {
            return response()->json([
                'success' => true,
                'data' => null,
                'experience' => $formExperience,
                'upstream_context' => $upstreamContext,
                'project_brief_assessment' => $projectBriefAssessment,
                'tool_briefing' => $toolBriefing,
            ]);
        }

        return response()->json([
            'success' => true,
            'experience' => $formExperience,
            'upstream_context' => $upstreamContext,
            'project_brief_assessment' => $projectBriefAssessment,
            'tool_briefing' => $toolBriefing,
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

    /**
     * @return array<int, array{tool_code: string, tool_name: string, headline: string, text: string, completeness: int}>
     */
    private function buildUpstreamContext(int $workspaceId, int $projectId, Tool $tool): array
    {
        $dependsOn = $tool->depends_on_json ?? [];

        if (empty($dependsOn)) {
            return [];
        }

        return \App\Domain\WorkspaceData\Models\WorkspaceData::query()
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $projectId)
            ->whereIn('key', collect($dependsOn)->map(fn (string $code) => 'tool.summary.'.$code)->all())
            ->get()
            ->map(fn (\App\Domain\WorkspaceData\Models\WorkspaceData $row) => [
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

    /**
     * @param  array<string, mixed>  $toolBriefing
     * @return array<string, mixed>
     */
    private function decorateToolBriefing(array $toolBriefing, Project $project): array
    {
        if ($toolBriefing === []) {
            return $toolBriefing;
        }

        $nextAction = $toolBriefing['next_action'] ?? [];
        $actionType = $nextAction['action_type'] ?? null;
        $ctaUrl = null;
        $ctaLabel = null;

        if ($actionType === 'current_tool') {
            $ctaUrl = '#tool-form';
            $ctaLabel = 'ابدأ تشغيل الأداة الآن';
        }

        if ($actionType === 'brief') {
            $ctaUrl = route('projects.brief.edit', $project);
            $ctaLabel = 'تعديل ملف المشروع';
        }

        if ($actionType === 'tool' && ! empty($nextAction['recommended_tool_code'])) {
            $recommendedTool = Tool::query()
                ->where('code', $nextAction['recommended_tool_code'])
                ->where('status', '!=', 'hidden')
                ->first();

            if ($recommendedTool) {
                $ctaUrl = route('tools.show', $recommendedTool);
                $ctaLabel = 'افتح '.($nextAction['recommended_tool_label'] ?? $recommendedTool->name ?? $recommendedTool->code);
            }
        }

        if ($actionType === 'tool' && empty($nextAction['recommended_tool_code']) && ! $ctaUrl) {
            $ctaUrl = route('projects.brief.edit', $project);
            $ctaLabel = 'تعديل ملف المشروع';
        }

        $toolBriefing['next_action'] = [
            ...$nextAction,
            'cta_url' => $ctaUrl,
            'cta_label' => $ctaLabel,
        ];

        return $toolBriefing;
    }
}
