<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $projects = Project::query()
            ->with(['workspace.account', 'client'])
            ->withCount('toolRuns')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->string('stage')->isNotEmpty(), fn ($query) => $query->where('stage', $request->integer('stage')))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects.index', ['projects' => $projects]);
    }

    public function show(Project $project): View
    {
        $project->load([
            'workspace.account.owner',
            'client',
            'toolRuns' => fn ($query) => $query->latest()->limit(20),
            'approvals.reviewer',
        ]);

        return view('admin.projects.show', [
            'project' => $project,
            'projectStatuses' => ['active', 'paused', 'completed', 'archived'],
        ]);
    }

    public function edit(Project $project): View
    {
        $project->load('workspace', 'client');

        return view('admin.projects.form', [
            'project' => $project,
            'workspaces' => Workspace::query()->orderBy('name')->get(),
            'method' => 'PUT',
            'action' => route('admin.projects.update', $project),
        ]);
    }

    public function update(Request $request, Project $project, FlashMessageCatalog $flash): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stage' => ['required', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'string', 'in:active,paused,completed,archived'],
        ]);

        $project->update($validated);

        $this->auditLogger->record(
            action: 'admin.project.updated',
            targetType: 'project',
            targetId: $project->id,
            actor: $request->user(),
            meta: ['name' => $project->name],
        );

        return back()->with('status', $flash->updated('المشروع'));
    }

    public function destroy(Project $project, FlashMessageCatalog $flash): RedirectResponse
    {
        $name = $project->name;
        $id = $project->id;
        $project->delete();

        $this->auditLogger->record(
            action: 'admin.project.deleted',
            targetType: 'project',
            targetId: $id,
            actor: request()->user(),
            meta: ['name' => $name],
        );

        return redirect()->route('admin.projects.index')->with('status', $flash->deleted('المشروع'));
    }
}
