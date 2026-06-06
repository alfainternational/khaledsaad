<?php

namespace App\Http\Controllers\Web;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Intelligence\Models\MonitorSnapshot;
use App\Domain\Approval\Models\Approval;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Controllers\Web\MarketingWebsiteController;
use App\Support\PlatformSectionCatalog;
use App\Support\Intelligence\ProjectIntelligenceRepository;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Tooling\ProjectActionAdvisor;
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
        ProjectMarketingBriefStore $briefStore,
        ProjectActionAdvisor $projectActionAdvisor,
        ProjectIntelligenceRepository $projectIntelligenceRepository,
    ): View {
        if (! $request->user()) {
            return app(MarketingWebsiteController::class)->guestStudio();
        }

        $workspace = $this->currentWorkspace($request);
        $context = $this->workspaceContext($request);

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with('client')
            ->orderBy('name')
            ->get();

        $briefMap = $briefStore->getMany($workspace, $projects);
        $briefAssessmentMap = collect($briefMap)
            ->map(fn (array $brief): array => $briefStore->assess($brief))
            ->all();
        $journeySnapshotMap = $journeyStore->getSnapshotMap($workspace, $projects);
        $readinessMap = $journeyStore->getReadinessMap($workspace, $projects);
        $latestAuditMap = $projectIntelligenceRepository->latestAuditMap($workspace, $projects);

        $projectActionMap = $projects
            ->mapWithKeys(function (Project $project) use ($briefMap, $briefAssessmentMap, $journeySnapshotMap, $projectActionAdvisor): array {
                return [
                    $project->id => $projectActionAdvisor->advise(
                        $project,
                        $briefMap[$project->id] ?? [],
                        $briefAssessmentMap[$project->id] ?? [],
                        $journeySnapshotMap[$project->id] ?? [],
                        [],
                    ),
                ];
            })
            ->all();
        $projectIntelligence = $projects
            ->mapWithKeys(function (Project $project) use ($latestAuditMap): array {
                $latestAudit = $latestAuditMap[$project->id] ?? null;
                return [
                    $project->id => [
                        'audit' => $latestAudit,
                        'summary' => $latestAudit?->summary_json ?? [],
                        'report' => $latestAudit?->report_json ?? [],
                    ],
                ];
            })
            ->all();

        return view('app.studio', [
            'workspace' => $workspace,
            'profile' => $profileStore->get($workspace),
            'studioEnabled' => $resolver->boolean('modules.ai_studio', $workspace),
            'newTemplatesEnabled' => $flags->isEnabled('ai_studio.new_templates', $context),
            'templates' => AITemplate::query()->where('status', 'published')->orderBy('credit_cost')->get(),
            'projects' => $projects,
            'projectBriefs' => $projects
                ->mapWithKeys(fn (Project $project): array => [
                    $project->id => [
                        'brief' => $briefMap[$project->id] ?? [],
                        'assessment' => $briefAssessmentMap[$project->id] ?? [],
                    ],
                ])
                ->all(),
            'projectContexts' => $projects
                ->mapWithKeys(function (Project $project) use ($journeySnapshotMap, $readinessMap): array {
                    return [
                        $project->id => [
                            'journey' => $journeySnapshotMap[$project->id] ?? [],
                            'readiness' => $readinessMap[$project->id] ?? [],
                        ],
                    ];
                })
                ->all(),
            'projectActions' => $projectActionMap,
            'projectIntelligence' => $projectIntelligence,
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
            return app(MarketingWebsiteController::class)->guestTemplates();
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
        ProjectMarketingBriefStore $briefStore,
        ProjectActionAdvisor $projectActionAdvisor,
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
        $projectBriefs = $projects->map(function (Project $project) use ($workspace, $briefStore): array {
            $brief = $briefStore->get($workspace, $project);
            $assessment = $briefStore->assess($brief);

            return [
                'project' => $project,
                'brief' => $brief,
                'assessment' => $assessment,
            ];
        });
        $projectActions = $projects->mapWithKeys(function (Project $project) use ($workspace, $briefStore, $journeyStore, $projectActionAdvisor): array {
            $brief = $briefStore->get($workspace, $project);
            $assessment = $briefStore->assess($brief);
            $toolSummaries = \App\Domain\WorkspaceData\Models\WorkspaceData::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $project->id)
                ->where('key', 'like', 'tool.summary.%')
                ->get()
                ->map(fn ($row) => ['tool_code' => str_replace('tool.summary.', '', $row->key)])
                ->all();

            return [
                $project->id => $projectActionAdvisor->advise(
                    $project,
                    $brief,
                    $assessment,
                    $journeyStore->getSnapshot($workspace, $project),
                    $toolSummaries,
                ),
            ];
        });
        $completedAuditRuns = AuditRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'completed')
            ->latest()
            ->get();
        $weakestIntelligenceDimensions = $completedAuditRuns
            ->map(function (AuditRun $auditRun): ?array {
                $scores = $auditRun->report_json['executive_scores'] ?? [];
                unset($scores['executive']);

                if ($scores === [] || ! is_array($scores)) {
                    return null;
                }

                asort($scores);
                $dimension = array_key_first($scores);

                if (! is_string($dimension)) {
                    return null;
                }

                return [
                    'dimension' => $dimension,
                    'score' => (int) ($scores[$dimension] ?? 0),
                ];
            })
            ->filter()
            ->countBy('dimension')
            ->sortDesc()
            ->take(6);
        $analysisIntegrityDistribution = $completedAuditRuns
            ->map(fn (AuditRun $auditRun): string => (string) ($auditRun->report_json['analysis_integrity']['status'] ?? 'unknown'))
            ->countBy()
            ->sortDesc();

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
            'auditRunsCount' => AuditRun::query()->where('workspace_id', $workspace->id)->count(),
            'averageExecutiveScore' => (int) round((float) $completedAuditRuns
                ->avg(fn (AuditRun $auditRun): int => (int) ($auditRun->report_json['executive_scores']['executive'] ?? 0))),
            'monitoredProjectsCount' => $workspace->projects()->where('monitoring_enabled', true)->count(),
            'pendingApprovalsCount' => (int) ($approvalStatusCounts['pending'] ?? 0),
            'approvedApprovalsCount' => (int) ($approvalStatusCounts['approved'] ?? 0),
            'projectReadiness' => $projectReadiness,
            'briefReadiness' => $projectBriefs,
            'projectActions' => $projectActions,
            'briefHealthAverage' => (int) round((float) $projectBriefs->avg(fn (array $item): int => (int) ($item['assessment']['completeness_score'] ?? 0))),
            'commonBriefGaps' => $projectBriefs
                ->flatMap(fn (array $item): array => $item['assessment']['missing_labels'] ?? [])
                ->countBy()
                ->sortDesc()
                ->take(6),
            'recentStructuredOutputs' => ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->with(['project.client', 'tool'])
                ->latest()
                ->limit(8)
                ->get(),
            'recentAuditRuns' => AuditRun::query()
                ->where('workspace_id', $workspace->id)
                ->with('project.client')
                ->latest()
                ->limit(8)
                ->get(),
            'monitorSnapshots' => MonitorSnapshot::query()
                ->where('workspace_id', $workspace->id)
                ->with('project.client')
                ->latest('captured_at')
                ->limit(8)
                ->get(),
            'weakestIntelligenceDimensions' => $weakestIntelligenceDimensions,
            'analysisIntegrityDistribution' => $analysisIntegrityDistribution,
        ]);
    }

    public function agency(
        Request $request,
        EntitlementResolver $resolver,
        FeatureFlagService $flags,
        ProjectMarketingBriefStore $briefStore,
    ): View
    {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('agency'));
        }

        $workspace = $this->currentWorkspace($request);
        $context = $this->workspaceContext($request);

        $clientHealth = $workspace->clients()
            ->with(['projects' => fn ($query) => $query->latest()->limit(5)])
            ->latest()
            ->get()
            ->map(function ($client) use ($workspace, $briefStore): array {
                $scores = $client->projects->map(function (Project $project) use ($workspace, $briefStore): int {
                    $assessment = $briefStore->assess($briefStore->get($workspace, $project));

                    return (int) ($assessment['completeness_score'] ?? 0);
                });

                return [
                    'client' => $client,
                    'brief_health' => $scores->isNotEmpty() ? (int) round((float) $scores->avg()) : 0,
                    'projects_count' => $client->projects->count(),
                ];
            });

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
            'clientHealth' => $clientHealth,
        ]);
    }
}
