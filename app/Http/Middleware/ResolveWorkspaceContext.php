<?php

namespace App\Http\Middleware;

use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = null;
        $user = $request->user();
        $workspaceLinks = collect();
        $currentWorkspaceRole = null;

        if ($user) {
            $workspaceLinks = $user->activeWorkspaces()
                ->with('account.subscription.plan')
                ->get();

            $workspaceId = $request->session()->get('current_workspace_id');

            $workspace = $this->resolveCurrentWorkspace($workspaceLinks, $workspaceId);

            if ($workspace) {
                $request->session()->put('current_workspace_id', $workspace->id);
                app()->instance('currentWorkspace', $workspace);
                $currentWorkspaceRole = $workspace->members()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->value('role');
            }
        }

        View::share('currentWorkspace', $workspace);
        View::share('workspaceLinks', $workspaceLinks);
        View::share('currentWorkspaceRole', $currentWorkspaceRole);

        // Journey progress for sidebar — cached 60s per workspace
        $journeyProgress = ['pct' => 0, 'completed' => 0, 'total' => 0];
        if ($workspace) {
            $journeyProgress = Cache::remember(
                'journey_progress_ws_' . $workspace->id,
                60,
                function () use ($workspace): array {
                    $totalTools = Tool::query()
                        ->whereIn('status', ['published', 'beta'])
                        ->count();
                    $currentProject = $workspace->projects()
                        ->latest('updated_at')
                        ->first();
                    $completedTools = $currentProject
                        ? ToolRun::query()
                            ->where('workspace_id', $workspace->id)
                            ->where('project_id', $currentProject->id)
                            ->distinct('tool_code')
                            ->count('tool_code')
                        : 0;
                    $pct = $totalTools > 0 ? (int) round(($completedTools / $totalTools) * 100) : 0;
                    return ['pct' => $pct, 'completed' => $completedTools, 'total' => $totalTools];
                }
            );
        }
        View::share('journeyProgress', $journeyProgress);

        return $next($request);
    }

    private function resolveCurrentWorkspace(Collection $workspaceLinks, int|string|null $workspaceId): ?Workspace
    {
        if ($workspaceLinks->isEmpty()) {
            return null;
        }

        if ($workspaceId !== null) {
            $currentWorkspace = $workspaceLinks->firstWhere('id', (int) $workspaceId);

            if ($currentWorkspace instanceof Workspace) {
                return $currentWorkspace;
            }
        }

        $firstWorkspace = $workspaceLinks->first();

        return $firstWorkspace instanceof Workspace ? $firstWorkspace : null;
    }
}
