<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Domain\Workspace\Models\Workspace;
use App\Support\Contexts\WorkspaceContext;
use Illuminate\Http\Request;

trait InteractsWithWorkspaceContext
{
    protected function currentWorkspace(Request $request): Workspace
    {
        /** @var Workspace|null $workspace */
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        abort_unless($workspace, 404, 'لا توجد مساحة عمل مرتبطة بحسابك حالياً.');

        return $workspace;
    }

    protected function workspaceContext(Request $request): WorkspaceContext
    {
        $workspace = $this->currentWorkspace($request);

        return new WorkspaceContext(
            $workspace,
            $request->user(),
            $workspace->account?->subscription?->plan,
        );
    }
}
