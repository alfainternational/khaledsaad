<?php

namespace App\Http\Controllers\Web;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\UpsertProjectRequest;
use App\Jobs\RunProjectIntelligenceAuditJob;
use App\Support\PlatformSectionCatalog;
use App\Support\Intelligence\MarketingIntelligenceService;
use App\Support\Intelligence\ProjectIntelligenceRepository;
use App\Support\Intelligence\SectorTemplateCatalog;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use App\Support\Workspaces\WorkspaceJourneyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function index(Request $request, OnboardingState $state): View|RedirectResponse
    {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('projects'));
        }

        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageProjects', $workspace);

        if (! $state->isCompleted($workspace)) {
            return redirect()->route('onboarding.show');
        }

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with('client')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->integer('stage') > 0, fn ($query) => $query->where('stage', $request->integer('stage')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('app.projects.index', [
            'workspace' => $workspace,
            'projects' => $projects,
        ]);
    }

    public function create(Request $request, SectorTemplateCatalog $sectorCatalog): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageProjects', $workspace);

        return view('app.projects.form', [
            'workspace' => $workspace,
            'project' => new Project,
            'clients' => Client::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'sectorOptions' => $sectorCatalog->options(),
            'action' => route('projects.store'),
            'method' => 'POST',
        ]);
    }

    public function store(UpsertProjectRequest $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageProjects', $workspace);
        $data = $request->validated();

        Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $data['client_id'] ?? null,
            'name' => $data['name'],
            'stage' => $data['stage'],
            'status' => $data['status'],
            'sector' => $data['sector'],
            'market_country' => $data['market_country'] ?? null,
            'primary_domain' => $data['primary_domain'] ?? null,
            'official_social_links_json' => $data['official_social_links_json'] ?? [],
            'verified_social_profiles_json' => $data['verified_social_profiles_json'] ?? [],
            'competitors_json' => $data['competitors_json'] ?? [],
            'analysis_goals_json' => $data['analysis_goals_json'] ?? [],
            'monitoring_enabled' => $data['monitoring_enabled'] ?? false,
        ]);

        return redirect()->route('projects.index')->with('status', $flash->created('المشروع'));
    }

    public function show(
        Request $request,
        Project $project,
        WorkspaceJourneyStore $journeyStore,
        ProjectMarketingBriefStore $briefStore,
        ProjectIntelligenceRepository $projectIntelligenceRepository,
    ): View {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('view', $project);
        $journeySnapshot = $journeyStore->getSnapshot($workspace, $project);
        $readiness = $journeyStore->getReadiness($workspace, $project);
        $brief = $briefStore->get($workspace, $project);
        $briefAssessment = $briefStore->assess($brief);

        $latestAudit = $projectIntelligenceRepository->latestAudit($project);

        return view('app.projects.show', [
            'workspace' => $workspace,
            'project' => $project
                ->load('client')
                ->loadCount(['toolRuns', 'approvals']),
            'journeySnapshot' => $journeySnapshot,
            'readiness' => $readiness,
            'brief' => $brief,
            'briefAssessment' => $briefAssessment,
            'latestAudit' => $latestAudit,
            'latestAuditReport' => $latestAudit?->report_json ?? [],
            'latestAuditSummary' => $latestAudit?->summary_json ?? [],
            'monitoringTrend' => $projectIntelligenceRepository->trend($project),
            'projectWorkspaceData' => WorkspaceData::query()
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $project->id)
                ->latest()
                ->limit(8)
                ->get(),
            'availableTools' => Tool::query()
                ->whereIn('status', ['published', 'beta'])
                ->orderBy('stage')
                ->orderBy('sort_order')
                ->get(),
            'recentRuns' => ToolRun::query()
                ->where('project_id', $project->id)
                ->with('tool')
                ->latest()
                ->limit(6)
                ->get(),
            'recentGenerations' => AIGeneration::query()
                ->where('project_id', $project->id)
                ->with('template')
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }

    public function edit(Request $request, Project $project, SectorTemplateCatalog $sectorCatalog): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('update', $project);

        return view('app.projects.form', [
            'workspace' => $workspace,
            'project' => $project,
            'clients' => Client::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'sectorOptions' => $sectorCatalog->options(),
            'action' => route('projects.update', $project),
            'method' => 'PUT',
        ]);
    }

    public function update(UpsertProjectRequest $request, Project $project, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $request->validated();

        $project->update([
            'client_id' => $data['client_id'] ?? null,
            'name' => $data['name'],
            'stage' => $data['stage'],
            'status' => $data['status'],
            'sector' => $data['sector'],
            'market_country' => $data['market_country'] ?? null,
            'primary_domain' => $data['primary_domain'] ?? null,
            'official_social_links_json' => $data['official_social_links_json'] ?? [],
            'verified_social_profiles_json' => $data['verified_social_profiles_json'] ?? [],
            'competitors_json' => $data['competitors_json'] ?? [],
            'analysis_goals_json' => $data['analysis_goals_json'] ?? [],
            'monitoring_enabled' => $data['monitoring_enabled'] ?? false,
        ]);

        return redirect()->route('projects.index')->with('status', $flash->updated('المشروع'));
    }

    public function destroy(Request $request, Project $project, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('status', $flash->deleted('المشروع'));
    }

    public function runAudit(
        Request $request,
        Project $project,
        MarketingIntelligenceService $marketingIntelligenceService,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('update', $project);

        $activeRun = $marketingIntelligenceService->activeRun($project);

        if ($activeRun) {
            return redirect()
                ->route('projects.show', $project)
                ->with('status', 'يوجد تحليل Intelligence قيد التنفيذ بالفعل لهذا المشروع.');
        }

        $auditRun = $marketingIntelligenceService->queue($project->fresh(), $workspace, 'manual');
        RunProjectIntelligenceAuditJob::dispatch($auditRun->id);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'تمت جدولة تحليل Marketing Intelligence وسيظهر تلقائياً عند اكتماله.');
    }

    /**
     * حالة آخر تدقيق للمشروع — يستخدمها الـ frontend لتحديث المسودّات تلقائياً عند اكتمال التدقيق غير المتزامن.
     */
    public function auditStatus(Request $request, Project $project): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('view', $project);

        $latest = AuditRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->latest()
            ->first();

        return response()->json([
            'status' => $latest?->status,
            'in_progress' => in_array($latest?->status, ['queued', 'running'], true),
        ]);
    }
}
