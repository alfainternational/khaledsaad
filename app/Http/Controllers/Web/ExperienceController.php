<?php

namespace App\Http\Controllers\Web;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Approval\Models\Approval;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\PlatformSectionCatalog;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExperienceController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function tools(
        Request $request,
        EntitlementResolver $resolver,
        WorkspaceProfileStore $profileStore,
        WorkspaceJourneyStore $journeyStore,
    ): View
    {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('tools'));
        }

        $workspace = $this->currentWorkspace($request);
        $currentProject = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with('client')
            ->latest('updated_at')
            ->first();
        $currentProjectRuns = $currentProject
            ? ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $currentProject->id)
                ->latest()
                ->get()
            : collect();
        $latestCurrentProjectRuns = $currentProjectRuns
            ->unique('tool_code')
            ->keyBy('tool_code');
        $completedToolCodes = $currentProjectRuns->pluck('tool_code')->unique()->all();
        $projectToolCounts = $currentProjectRuns
            ->groupBy('tool_code')
            ->map(fn ($runs) => $runs->count());
        $workspaceRunCounts = ToolRun::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('tool_code, COUNT(*) as aggregate')
            ->groupBy('tool_code')
            ->pluck('aggregate', 'tool_code');

        $tools = Tool::query()
            ->whereIn('status', ['published', 'beta'])
            ->orderBy('stage')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Tool $tool) use ($resolver, $workspace, $completedToolCodes, $projectToolCounts, $workspaceRunCounts, $latestCurrentProjectRuns) {
                $latestRun = $latestCurrentProjectRuns->get($tool->code);

                $tool->unlocked = $resolver->boolean('modules.stage_'.$tool->stage, $workspace, $tool->stage === 1);
                $tool->completed_in_current_project = in_array($tool->code, $completedToolCodes, true);
                $tool->current_project_runs = (int) ($projectToolCounts[$tool->code] ?? 0);
                $tool->workspace_runs = (int) ($workspaceRunCounts[$tool->code] ?? 0);
                $tool->available_modes = collect([
                    $tool->has_guided_mode ? 'guided' : null,
                    $tool->has_structured_mode ? 'structured' : null,
                    $tool->has_expert_mode ? 'expert' : null,
                ])->filter()->values()->all();
                $tool->available_mode_labels = collect($tool->available_modes)
                    ->map(fn (string $mode): string => match ($mode) {
                        'guided' => 'بسيط',
                        'structured' => 'مرتّب',
                        'expert' => 'مفصّل',
                        default => $mode,
                    })
                    ->all();
                $tool->latest_current_project_summary = $latestRun?->summary_json['headline']
                    ?? $latestRun?->output_json['headline']
                    ?? null;
                $tool->latest_current_project_run_at = $latestRun?->created_at;

                return $tool;
            });

        $workspace->loadMissing('account.subscription.plan');

        $unlockedStages = [];
        for ($stage = 1; $stage <= 5; $stage++) {
            if ($resolver->boolean('modules.stage_'.$stage, $workspace, $stage === 1)) {
                $unlockedStages[] = $stage;
            }
        }
        $lockedStages = array_values(array_diff(range(1, 5), $unlockedStages));

        $planDisplayName = $workspace->account?->subscription?->plan?->name_ar
            ?? $workspace->account?->subscription?->plan?->name_en
            ?? $workspace->account?->subscription?->plan?->code;

        return view('app.tools', [
            'workspace' => $workspace,
            'currentProject' => $currentProject,
            'profile' => $profileStore->get($workspace),
            'journeySnapshot' => $currentProject
                ? $journeyStore->getSnapshot($workspace, $currentProject)
                : [],
            'tools' => $tools,
            'toolRunsCount' => ToolRun::query()->where('workspace_id', $workspace->id)->count(),
            'unlockedStages' => $unlockedStages,
            'lockedStages' => $lockedStages,
            'planDisplayName' => $planDisplayName,
        ]);
    }

    public function studio(
        Request $request,
        EntitlementResolver $resolver,
        FeatureFlagService $flags,
        WorkspaceProfileStore $profileStore,
        WorkspaceJourneyStore $journeyStore,
    ): View {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('studio'));
        }

        $workspace = $this->currentWorkspace($request);
        $context = $this->workspaceContext($request);

        return view('app.studio', [
            'workspace' => $workspace,
            'profile' => $profileStore->get($workspace),
            'studioEnabled' => $resolver->boolean('modules.ai_studio', $workspace),
            'newTemplatesEnabled' => $flags->isEnabled('ai_studio.new_templates', $context),
            'templates' => AITemplate::query()->where('status', 'published')->orderBy('credit_cost')->get(),
            'projects' => Project::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'projectContexts' => Project::query()
                ->where('workspace_id', $workspace->id)
                ->with('client')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function (Project $project) use ($workspace, $journeyStore): array {
                    return [
                        $project->id => [
                            'journey' => $journeyStore->getSnapshot($workspace, $project),
                            'readiness' => $journeyStore->getReadiness($workspace, $project),
                        ],
                    ];
                })
                ->all(),
            'recentGenerations' => AIGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->with('template')
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }

    public function templates(Request $request): View
    {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('templates'));
        }

        return view('app.templates', [
            'workspace' => $this->currentWorkspace($request),
            'templates' => AITemplate::query()->where('status', 'published')->orderBy('credit_cost')->get(),
        ]);
    }

    public function reports(
        Request $request,
        WorkspaceJourneyStore $journeyStore,
        WorkspaceProfileStore $profileStore,
    ): View {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('reports'));
        }

        $workspace = $this->currentWorkspace($request);
        $projects = $workspace->projects()->with('client')->get();
        $approvalStatusCounts = Approval::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $projectReadiness = $projects->map(function (Project $project) use ($workspace, $journeyStore): array {
            $readiness = $journeyStore->getReadiness($workspace, $project);

            return [
                'project' => $project,
                'journey' => $journeyStore->getSnapshot($workspace, $project),
                'readiness' => $readiness,
                'average_score' => collect($readiness)->avg('score') ? (int) round((float) collect($readiness)->avg('score')) : 0,
            ];
        });

        return view('app.reports', [
            'workspace' => $workspace,
            'profile' => $profileStore->get($workspace),
            'stageDistribution' => $workspace->projects()
                ->selectRaw('stage, count(*) as aggregate')
                ->groupBy('stage')
                ->orderBy('stage')
                ->pluck('aggregate', 'stage'),
            'statusDistribution' => $workspace->projects()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'clientDistribution' => $workspace->clients()
                ->withCount('projects')
                ->orderByDesc('projects_count')
                ->limit(8)
                ->get(),
            'toolUsageByStage' => ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->selectRaw('tools.stage as stage, count(tool_runs.id) as aggregate')
                ->join('tools', 'tools.code', '=', 'tool_runs.tool_code')
                ->groupBy('tools.stage')
                ->orderBy('tools.stage')
                ->pluck('aggregate', 'stage'),
            'toolRunsCount' => ToolRun::query()->where('workspace_id', $workspace->id)->count(),
            'aiGenerationsCount' => AIGeneration::query()->where('workspace_id', $workspace->id)->count(),
            'pendingApprovalsCount' => (int) ($approvalStatusCounts['pending'] ?? 0),
            'approvedApprovalsCount' => (int) ($approvalStatusCounts['approved'] ?? 0),
            'projectReadiness' => $projectReadiness,
            'recentStructuredOutputs' => ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->with(['project.client', 'tool'])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function agency(Request $request, EntitlementResolver $resolver, FeatureFlagService $flags): View
    {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('agency'));
        }

        $workspace = $this->currentWorkspace($request);
        $context = $this->workspaceContext($request);

        return view('app.agency', [
            'workspace' => $workspace,
            'agencyEntitled' => $resolver->boolean('modules.agency_mode', $workspace),
            'agencyFlagEnabled' => $flags->isEnabled('agency.beta_workspace', $context),
            'clients' => $workspace->clients()->withCount('projects')->latest()->limit(8)->get(),
            'pendingApprovals' => Approval::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'pending')
                ->with('project.client')
                ->latest()
                ->limit(6)
                ->get(),
            'clientSummaries' => $workspace->clients()
                ->withCount('projects')
                ->with(['projects' => fn ($query) => $query->latest()->limit(3)])
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }
}
