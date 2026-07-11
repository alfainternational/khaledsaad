<?php

namespace App\Support\Dashboard;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\Approval\Models\Approval;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;

class DashboardResolver
{
    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
        private readonly WorkspaceJourneyStore $journeyStore,
        private readonly ProjectMarketingBriefStore $projectMarketingBriefStore,
        private readonly NextStepRecommendationService $nextStepRecommendationService,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly EntitlementResolver $entitlementResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Workspace $workspace, User $user): array
    {
        $workspace->loadMissing('account.subscription.plan', 'members');
        $projectSummary = $workspace->projects()
            ->selectRaw('stage, status, COUNT(*) as aggregate')
            ->groupBy('stage', 'status')
            ->get();

        $profile = $this->profileStore->get($workspace);
        $persona = $profile['persona'] ?? PersonaCatalog::inferFromWorkspaceType($workspace->type);
        $awarenessLevel = $profile['awareness_level'] ?? PersonaCatalog::defaultAwareness($persona);
        $role = (string) optional(
            $workspace->members->firstWhere('user_id', $user->id)
        )->role ?: 'viewer';
        $projectsCount = (int) $projectSummary->sum('aggregate');
        $activeProjectsCount = (int) $projectSummary
            ->where('status', 'active')
            ->sum('aggregate');
        $advancedProjectsCount = (int) $projectSummary
            ->filter(fn ($project) => (int) $project->stage >= 4)
            ->sum('aggregate');
        $clientsCount = $workspace->clients()->count();
        $membersCount = $workspace->members->count();

        $pendingApprovalsCount = Approval::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'pending')
            ->count();

        $metrics = [
            'projects' => $projectsCount,
            'active_projects' => $activeProjectsCount,
            'advanced_projects' => $advancedProjectsCount,
            'clients' => $clientsCount,
            'members' => $membersCount,
            'tool_runs' => ToolRun::query()->where('workspace_id', $workspace->id)->count(),
            'ai_generations' => AIGeneration::query()->where('workspace_id', $workspace->id)->count(),
            'pending_approvals' => $pendingApprovalsCount,
            'enabled_flags' => FeatureFlag::query()->whereIn('status', ['on', 'beta'])->count(),
        ];

        $currentProject = $workspace->projects()
            ->with('client')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->orderByDesc('id')
            ->first();
        $currentProjectToolRuns = $currentProject
            ? ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $currentProject->id)
                ->latest()
                ->get()
            : collect();
        $latestCurrentProjectRuns = $currentProjectToolRuns
            ->unique('tool_code')
            ->keyBy('tool_code');
        $completedToolCodes = $currentProjectToolRuns
            ->pluck('tool_code')
            ->unique()
            ->values()
            ->all();
        $toolRunCounts = $currentProjectToolRuns
            ->groupBy('tool_code')
            ->map(fn ($runs) => $runs->count());
        $journeySnapshot = $currentProject
            ? $this->journeyStore->getSnapshot($workspace, $currentProject)
            : [];
        $readiness = $currentProject
            ? $this->journeyStore->getReadiness($workspace, $currentProject)
            : [];
        $currentProjectBrief = $currentProject
            ? $this->projectMarketingBriefStore->get($workspace, $currentProject)
            : [];
        $briefAssessment = $currentProject
            ? $this->projectMarketingBriefStore->assess($currentProjectBrief)
            : [
                'completeness_score' => 0,
                'known_fields' => 0,
                'total_fields' => 0,
                'next_actions' => ['ابدأ ببناء ملف مشروعك التسويقي حتى تظهر لك التوصيات التالية بوضوح.'],
            ];

        $stageProgress = collect(StageCatalog::all())
            ->map(function (array $stage, int $stageNumber) use ($projectSummary): array {
                $projects = $projectSummary->where('stage', $stageNumber);

                return [
                    'number' => $stageNumber,
                    'label' => $stage['label'],
                    'description' => $stage['description'],
                    'projects_count' => (int) $projects->sum('aggregate'),
                    'completed' => (int) $projects->where('status', 'completed')->sum('aggregate'),
                ];
            })
            ->values()
            ->all();

        $toolPipeline = collect(StageCatalog::all())
            ->map(function (array $stage, int $stageNumber) use ($workspace, $completedToolCodes, $toolRunCounts, $latestCurrentProjectRuns): array {
                $unlocked = $this->entitlementResolver->boolean('modules.stage_'.$stageNumber, $workspace, $stageNumber === 1);
                $tools = Tool::query()
                    ->where('stage', $stageNumber)
                    ->whereIn('status', ['published', 'beta'])
                    ->orderBy('sort_order')
                    ->get()
                    ->map(function (Tool $tool) use ($completedToolCodes, $toolRunCounts, $unlocked, $latestCurrentProjectRuns): array {
                        $completed = in_array($tool->code, $completedToolCodes, true);
                        $latestRun = $latestCurrentProjectRuns->get($tool->code);

                        return [
                            'code' => $tool->code,
                            'name' => $tool->name ?: $tool->code,
                            'route' => route('tools.show', $tool),
                            'completed' => $completed,
                            'runs_count' => (int) ($toolRunCounts[$tool->code] ?? 0),
                            'unlocked' => $unlocked,
                            'output_type' => $tool->output_type,
                            'latest_summary' => $latestRun?->summary_json['headline']
                                ?? $latestRun?->output_json['headline']
                                ?? null,
                        ];
                    })
                    ->all();

                return [
                    'stage' => $stageNumber,
                    'label' => $stage['label'],
                    'description' => $stage['description'],
                    'completed' => collect($tools)->where('completed', true)->count(),
                    'total' => count($tools),
                    'remaining' => collect($tools)->where('completed', false)->where('unlocked', true)->count(),
                    'tools' => $tools,
                ];
            })
            ->values()
            ->all();

        $averageReadiness = collect($readiness)->avg('score');
        $showClientsCenter = in_array($workspace->type, ['agency'], true)
            || in_array($persona, ['freelancer', 'business', 'agency'], true)
            || $clientsCount > 0;
        $showTeamCenter = in_array($workspace->type, ['team', 'agency'], true)
            || $membersCount > 1;

        $actionCenters = collect([
            [
                'title' => 'المشاريع',
                'summary' => 'عدد المشاريع النشطة داخل المساحة الحالية.',
                'value' => $metrics['active_projects'],
                'route' => route('projects.index'),
                'label' => 'افتح المشاريع',
            ],
            [
                'title' => 'الأدوات',
                'summary' => $currentProject
                    ? 'الأدوات المكتملة داخل المشروع الحالي '.$currentProject->name.'.'
                    : 'ابدأ مشروعاً أولاً لتظهر سلسلة الأدوات المكتملة.',
                'value' => count($completedToolCodes),
                'route' => route('tools.index'),
                'label' => 'افتح الأدوات',
            ],
            [
                'title' => 'الاستوديو',
                'summary' => 'المسودات المولدة من السياق الفعلي للمشاريع.',
                'value' => $metrics['ai_generations'],
                'route' => route('studio.index'),
                'label' => 'افتح الاستوديو',
                'visible' => $this->entitlementResolver->boolean('modules.ai_studio', $workspace),
            ],
            [
                'title' => 'الاعتمادات',
                'summary' => 'العناصر التي تنتظر مراجعة أو قراراً داخل المساحة.',
                'value' => $metrics['pending_approvals'],
                'route' => route('approvals.index'),
                'label' => 'افتح الاعتمادات',
            ],
            [
                'title' => 'التقارير',
                'summary' => 'قراءة جاهزية المشاريع وتوزيع العمل والمخرجات الحديثة.',
                'value' => $averageReadiness ? (int) round((float) $averageReadiness) : 0,
                'route' => route('reports.index'),
                'label' => 'افتح التقارير',
            ],
            [
                'title' => 'العملاء',
                'summary' => 'الحسابات أو الجهات المرتبطة بالتنفيذ الحالي.',
                'value' => $metrics['clients'],
                'route' => route('clients.index'),
                'label' => 'افتح العملاء',
                'visible' => $showClientsCenter,
            ],
            [
                'title' => 'الفريق',
                'summary' => 'الأعضاء المشاركون في تشغيل هذه المساحة.',
                'value' => $metrics['members'],
                'route' => route('team.index'),
                'label' => 'افتح الفريق',
                'visible' => $showTeamCenter,
            ],
        ])->filter(fn (array $center): bool => $center['visible'] ?? true)
            ->values()
            ->all();

        return [
            'persona' => $persona,
            'personaLabel' => PersonaCatalog::label($persona),
            'personaDescription' => PersonaCatalog::description($persona),
            'awarenessLevel' => $awarenessLevel,
            'awarenessLabel' => AwarenessCatalog::label($awarenessLevel),
            'role' => $role,
            'currentProject' => $currentProject,
            'profile' => $profile,
            'journeySnapshot' => $journeySnapshot,
            'readiness' => $readiness,
            'brief' => $currentProjectBrief,
            'briefAssessment' => $briefAssessment,
            'toolPipeline' => $toolPipeline,
            'actionCenters' => $actionCenters,
            'metrics' => $metrics,
            'stageProgress' => $stageProgress,
            'nextStep' => $this->nextStepRecommendationService->forWorkspace($workspace, $currentProject),
            'personaWidgets' => $this->widgetRegistry->personaWidgets($persona, $metrics, $awarenessLevel),
            'roleWidgets' => $this->widgetRegistry->roleWidgets($role),
            'entitlements' => $workspace->account?->subscription?->plan
                ? $this->entitlementResolver->allForPlan($workspace->account->subscription->plan)
                : [],
            'recentToolRuns' => ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->with(['project.client', 'author', 'tool'])
                ->latest()
                ->limit(5)
                ->get(),
            'recentProjects' => $workspace->projects()
                ->with('client')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
            'recentClients' => $workspace->clients()
                ->withCount('projects')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
            'recentApprovals' => Approval::query()
                ->where('workspace_id', $workspace->id)
                ->with(['project.client', 'reviewer'])
                ->latest()
                ->limit(5)
                ->get(),
            'recentGenerations' => AIGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->with('template')
                ->latest()
                ->limit(4)
                ->get(),
        ];
    }
}
