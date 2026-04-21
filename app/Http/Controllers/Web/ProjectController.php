<?php

namespace App\Http\Controllers\Web;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\UpsertProjectRequest;
use App\Support\PlatformSectionCatalog;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use App\Support\Workspaces\WorkspaceJourneyStore;
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

    public function create(Request $request): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageProjects', $workspace);

        return view('app.projects.form', [
            'workspace' => $workspace,
            'project' => new Project,
            'clients' => Client::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(),
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
        ]);

        return redirect()->route('projects.index')->with('status', $flash->created('المشروع'));
    }

    public function show(
        Request $request,
        Project $project,
        WorkspaceJourneyStore $journeyStore,
    ): View {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('view', $project);
        $journeySnapshot = $journeyStore->getSnapshot($workspace, $project);
        $readiness = $journeyStore->getReadiness($workspace, $project);

        return view('app.projects.show', [
            'workspace' => $workspace,
            'project' => $project
                ->load('client')
                ->loadCount(['toolRuns', 'approvals']),
            'journeySnapshot' => $journeySnapshot,
            'readiness' => $readiness,
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

    public function edit(Request $request, Project $project): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('update', $project);

        return view('app.projects.form', [
            'workspace' => $workspace,
            'project' => $project,
            'clients' => Client::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(),
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
        ]);

        return redirect()->route('projects.index')->with('status', $flash->updated('المشروع'));
    }

    public function destroy(Request $request, Project $project, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('status', $flash->deleted('المشروع'));
    }
}
