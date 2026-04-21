<?php

namespace App\Http\Middleware;

use App\Domain\Workspace\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiWorkspaceMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $publicId = (string) $request->route('workspace_public_id', '');
        $workspace = Workspace::query()->where('public_id', $publicId)->first();
        abort_unless($workspace, 404);

        $user = $request->user();
        abort_unless($user, 401);

        $role = $workspace->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');

        abort_unless($role !== null, 403);

        $token = $user->currentAccessToken();
        if ($token !== null && $token->abilities !== [] && $token->abilities !== null) {
            $hasWildcard = in_array('*', $token->abilities, true);
            $scoped = 'workspace:'.$workspace->public_id;
            if (! $hasWildcard && ! in_array($scoped, $token->abilities, true)) {
                abort(403, 'نطاق التوكن لا يشمل هذه مساحة العمل.');
            }
        }

        app()->instance('currentWorkspace', $workspace);

        return $next($request);
    }
}
