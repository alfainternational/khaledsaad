<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Workspaces\DeleteWorkspaceEntitlementAction;
use App\Application\Admin\Workspaces\UpsertWorkspaceEntitlementAction;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkspaceEntitlementRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkspaceEntitlementController extends Controller
{
    public function index(Workspace $workspace, EntitlementResolver $resolver): View
    {
        $workspace->load('account.subscription.plan');
        $plan = $workspace->account?->subscription?->plan;

        return view('admin.workspaces.entitlements', [
            'workspace' => $workspace,
            'workspaceEntitlements' => Entitlement::query()
                ->where('scope_type', 'workspace')
                ->where('scope_id', $workspace->getKey())
                ->orderBy('key')
                ->get(),
            'planEntitlements' => $plan ? $resolver->allForPlan($plan) : [],
        ]);
    }

    public function store(
        WorkspaceEntitlementRequest $request,
        Workspace $workspace,
        UpsertWorkspaceEntitlementAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $action->handle($workspace, $request->validated(), $request->user());

        return back()->with('status', $flash->entitlementOverrideSaved());
    }

    public function destroy(
        Workspace $workspace,
        Entitlement $entitlement,
        DeleteWorkspaceEntitlementAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        abort_unless($entitlement->scope_type === 'workspace' && $entitlement->scope_id === $workspace->getKey(), 404);

        $action->handle($workspace, $entitlement, request()->user());

        return back()->with('status', $flash->entitlementOverrideDeleted());
    }
}
