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

    /**
     * مساحة العمل الصحيحة لعنصر محدد: إن كان العنصر يتبع مساحة أخرى والمستخدم
     * عضو نشط فيها، تُبدَّل الجلسة إليها تلقائياً بدل 403 جاف — رابط المخرج
     * يعمل دائماً لصاحبه مهما كانت المساحة النشطة وقت الفتح (تعدد التبويبات).
     * غير العضو يُرفض 403 كما كان.
     */
    protected function workspaceForItem(Request $request, ?int $itemWorkspaceId): Workspace
    {
        $current = $this->currentWorkspace($request);
        if ($itemWorkspaceId === null || (int) $current->id === (int) $itemWorkspaceId) {
            return $current;
        }

        /** @var Workspace|null $itemWorkspace */
        $itemWorkspace = $request->user()
            ?->activeWorkspaces()
            ->with('account.subscription.plan')
            ->get()
            ->firstWhere('id', (int) $itemWorkspaceId);

        abort_unless($itemWorkspace instanceof Workspace, 403, 'هذا العنصر يتبع مساحة عمل لست عضواً فيها.');

        $request->session()->put('current_workspace_id', $itemWorkspace->id);
        app()->instance('currentWorkspace', $itemWorkspace);

        return $itemWorkspace;
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
