<?php

namespace App\Http\Controllers\Web;

use App\Application\Tooling\RunToolAction;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\ExecuteToolRequest;
use App\Support\Tooling\ToolCopyCatalog;
use App\Support\Tooling\ToolModePolicy;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\RedirectResponse;

class ToolRunController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function store(
        ExecuteToolRequest $request,
        Project $project,
        Tool $tool,
        RunToolAction $action,
        ToolModePolicy $toolModePolicy,
        WorkspaceProfileStore $profileStore,
        ToolCopyCatalog $toolCopyCatalog,
    ): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);
        $this->authorize('view', $project);
        abort_unless($tool->status !== 'hidden', 404);
        $profile = $profileStore->get($workspace);
        $requestedMode = (string) $request->validated('mode');
        $resolvedMode = $this->resolveModeForProject(
            $tool,
            $project,
            $requestedMode,
            $workspace->id,
            $toolModePolicy,
            $profile['awareness_level'] ?? null,
        );

        if ($resolvedMode === null) {
            return back()
                ->withInput()
                ->withErrors(['mode' => $toolCopyCatalog->modeLockedMessage()]);
        }

        $action->handle(
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

        return redirect()->route('projects.show', $project)->with('status', $toolCopyCatalog->successMessageForTool($tool));
    }

    public function storeFromTool(
        ExecuteToolRequest $request,
        Tool $tool,
        RunToolAction $action,
        ToolModePolicy $toolModePolicy,
        WorkspaceProfileStore $profileStore,
        ToolCopyCatalog $toolCopyCatalog,
    ): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);
        abort_unless($tool->status !== 'hidden', 404);

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($request->validated('project_id'));

        $this->authorize('view', $project);
        $profile = $profileStore->get($workspace);
        $requestedMode = (string) $request->validated('mode');
        $resolvedMode = $this->resolveModeForProject(
            $tool,
            $project,
            $requestedMode,
            $workspace->id,
            $toolModePolicy,
            $profile['awareness_level'] ?? null,
        );

        if ($resolvedMode === null) {
            return back()
                ->withInput()
                ->withErrors(['mode' => $toolCopyCatalog->modeLockedMessage()]);
        }

        $action->handle(
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

        return redirect()->route('tools.show', $tool)->with('status', $toolCopyCatalog->successMessageForTool($tool));
    }

    private function resolveModeForProject(
        Tool $tool,
        Project $project,
        string $requestedMode,
        int $workspaceId,
        ToolModePolicy $toolModePolicy,
        ?string $awareness,
    ): ?string {
        $runs = ToolRun::query()
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $project->id)
            ->where('tool_code', $tool->code)
            ->latest()
            ->get();

        return $toolModePolicy->resolveMode(
            $tool,
            $requestedMode,
            $runs->first(),
            $runs->count(),
            $awareness,
        );
    }
}
