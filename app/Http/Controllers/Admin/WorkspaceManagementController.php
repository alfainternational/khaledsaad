<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Workspaces\DeleteWorkspaceAction;
use App\Application\Admin\Workspaces\SetWorkspaceStatusAction;
use App\Application\Admin\Workspaces\UpsertWorkspaceAction;
use App\Domain\Account\Models\Account;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWorkspaceStatusRequest;
use App\Http\Requests\Admin\UpsertWorkspaceRequest;
use App\Models\User;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceManagementController extends Controller
{
    public function index(Request $request): View
    {
        $workspaces = Workspace::query()
            ->with('account.subscription.plan')
            ->withCount(['members', 'projects', 'clients'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->string('type')->isNotEmpty(), fn ($query) => $query->where('type', $request->string('type')->value()))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.workspaces.index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function create(): View
    {
        return view('admin.workspaces.form', [
            'workspace' => new Workspace(['type' => 'personal', 'status' => 'active']),
            'accounts' => Account::query()->with('owner', 'subscription.plan')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'method' => 'POST',
            'action' => route('admin.workspaces.store'),
        ]);
    }

    public function store(
        UpsertWorkspaceRequest $request,
        UpsertWorkspaceAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $workspace = $action->handle($request->validated(), $request->user());

        return redirect()->route('admin.workspaces.show', $workspace)->with('status', $flash->created('مساحة العمل'));
    }

    public function show(Workspace $workspace): View
    {
        $workspace->load([
            'account.owner',
            'account.subscription.plan',
            'members.user',
            'projects.client',
            'clients',
        ])->loadCount(['members', 'projects', 'clients']);

        $planId = $workspace->account?->subscription?->plan_id;

        return view('admin.workspaces.show', [
            'workspace' => $workspace,
            'workspaceStatuses' => ['active', 'paused', 'archived'],
            'planEntitlements' => $planId
                ? Entitlement::query()
                    ->where('scope_type', 'plan')
                    ->where('scope_id', $planId)
                    ->orderBy('key')
                    ->get()
                : collect(),
            'workspaceOverrides' => Entitlement::query()
                ->where('scope_type', 'workspace')
                ->where('scope_id', $workspace->getKey())
                ->orderBy('key')
                ->get(),
        ]);
    }

    public function edit(Workspace $workspace): View
    {
        $workspace->load('members.user');

        return view('admin.workspaces.form', [
            'workspace' => $workspace,
            'accounts' => Account::query()->with('owner', 'subscription.plan')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'method' => 'PUT',
            'action' => route('admin.workspaces.update', $workspace),
        ]);
    }

    public function update(
        UpsertWorkspaceRequest $request,
        Workspace $workspace,
        UpsertWorkspaceAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $action->handle($request->validated(), $request->user(), $workspace);

        return redirect()->route('admin.workspaces.show', $workspace)->with('status', $flash->updated('مساحة العمل'));
    }

    public function updateStatus(
        UpdateWorkspaceStatusRequest $request,
        Workspace $workspace,
        SetWorkspaceStatusAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $action->handle($workspace, $request->validated('status'), $request->user());

        return back()->with('status', $flash->statusUpdated('مساحة العمل'));
    }

    public function destroy(Workspace $workspace, DeleteWorkspaceAction $action, FlashMessageCatalog $flash): RedirectResponse
    {
        $action->handle($workspace, request()->user());

        return redirect()->route('admin.workspaces.index')->with('status', $flash->deleted('مساحة العمل'));
    }
}
