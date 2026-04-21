<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Accounts\DeleteAccountAction;
use App\Application\Admin\Accounts\SetAccountStatusAction;
use App\Application\Admin\Accounts\UpdateAccountSubscriptionAction;
use App\Application\Admin\Accounts\UpsertAccountAction;
use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccountStatusRequest;
use App\Http\Requests\Admin\UpdateAccountSubscriptionRequest;
use App\Http\Requests\Admin\UpsertAccountRequest;
use App\Models\User;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountManagementController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = Account::query()
            ->with(['owner', 'subscription.plan'])
            ->withCount('workspaces')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('billing_email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.accounts.index', ['accounts' => $accounts]);
    }

    public function create(): View
    {
        return view('admin.accounts.form', [
            'account' => new Account(['status' => 'active']),
            'users' => User::query()->orderBy('name')->get(),
            'plans' => Plan::query()->orderBy('monthly_price')->get(),
            'subscriptionStatuses' => ['trialing', 'active', 'past_due', 'canceled', 'pending_payment'],
            'method' => 'POST',
            'action' => route('admin.accounts.store'),
        ]);
    }

    public function store(
        UpsertAccountRequest $request,
        UpsertAccountAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $account = $action->handle($request->validated(), $request->user());

        return redirect()->route('admin.accounts.show', $account)->with('status', $flash->created('الحساب'));
    }

    public function show(Account $account): View
    {
        $account->load([
            'owner',
            'subscription.plan',
            'workspaces' => fn ($query) => $query
                ->withCount(['members', 'projects', 'clients'])
                ->latest(),
        ]);

        return view('admin.accounts.show', [
            'account' => $account,
            'plans' => Plan::query()->orderBy('monthly_price')->get(),
            'accountStatuses' => ['active', 'suspended', 'archived'],
            'subscriptionStatuses' => ['trialing', 'active', 'past_due', 'canceled', 'pending_payment'],
        ]);
    }

    public function edit(Account $account): View
    {
        $account->load('subscription.plan');

        return view('admin.accounts.form', [
            'account' => $account,
            'users' => User::query()->orderBy('name')->get(),
            'plans' => Plan::query()->orderBy('monthly_price')->get(),
            'subscriptionStatuses' => ['trialing', 'active', 'past_due', 'canceled', 'pending_payment'],
            'method' => 'PUT',
            'action' => route('admin.accounts.update', $account),
        ]);
    }

    public function update(
        UpsertAccountRequest $request,
        Account $account,
        UpsertAccountAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $account);

        return redirect()->route('admin.accounts.show', $account)->with('status', $flash->updated('الحساب'));
    }

    public function updateStatus(
        UpdateAccountStatusRequest $request,
        Account $account,
        SetAccountStatusAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $action->handle($account, $request->validated('status'), $request->user());

        return back()->with('status', $flash->statusUpdated('الحساب'));
    }

    public function updateSubscription(
        UpdateAccountSubscriptionRequest $request,
        Account $account,
        UpdateAccountSubscriptionAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $action->handle($account, $request->validated(), $request->user());

        return back()->with('status', $flash->subscriptionUpdated());
    }

    public function destroy(Account $account, DeleteAccountAction $action, FlashMessageCatalog $flash): RedirectResponse
    {
        $action->handle($account, request()->user());

        return redirect()->route('admin.accounts.index')->with('status', $flash->deleted('الحساب'));
    }
}
