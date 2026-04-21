<?php

namespace App\Http\Middleware;

use App\Domain\Project\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiProjectInWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $projectPublicId = (string) $request->route('project_public_id', '');

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $projectPublicId)
            ->first();

        abort_unless($project, 404);

        $request->merge(['project_id' => $project->id]);

        return $next($request);
    }
}
