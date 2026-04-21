<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Plans\DeletePlanAction;
use App\Application\Admin\Plans\UpsertPlanAction;
use App\Domain\Billing\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertPlanRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('monthly_price')->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', [
            'plan' => new Plan(['status' => 'active']),
            'modules' => config('module_registry'),
            'entitlements' => [],
            'method' => 'POST',
            'action' => route('admin.plans.store'),
        ]);
    }

    public function store(
        UpsertPlanRequest $request,
        UpsertPlanAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $plan = $action->handle($request->validated(), $request->user());

        return redirect()->route('admin.plans.edit', $plan)->with('status', $flash->created('الباقة'));
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', [
            'plan' => $plan,
            'modules' => config('module_registry'),
            'entitlements' => $plan->entitlements()
                ->orderBy('key')
                ->get(),
            'method' => 'PUT',
            'action' => route('admin.plans.update', $plan),
        ]);
    }

    public function update(
        UpsertPlanRequest $request,
        Plan $plan,
        UpsertPlanAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $plan);

        return back()->with('status', $flash->updated('الباقة'));
    }

    public function destroy(Plan $plan, DeletePlanAction $action, FlashMessageCatalog $flash): RedirectResponse
    {
        $action->handle($plan, request()->user());

        return redirect()->route('admin.plans.index')->with('status', $flash->deleted('الباقة'));
    }
}
