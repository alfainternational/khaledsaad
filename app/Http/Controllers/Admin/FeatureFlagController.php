<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\FeatureFlags\DeleteFeatureFlagAction;
use App\Application\Admin\FeatureFlags\UpsertFeatureFlagAction;
use App\Domain\Billing\Models\Plan;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertFeatureFlagRequest;
use App\Models\User;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FeatureFlagController extends Controller
{
    public function index(): View
    {
        return view('admin.feature-flags.index', [
            'featureFlags' => FeatureFlag::query()->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('admin.feature-flags.form', [
            'featureFlag' => new FeatureFlag(['status' => 'off', 'rollout_percentage' => 100]),
            'audiences' => [],
            'method' => 'POST',
            'action' => route('admin.feature-flags.store'),
            'modules' => config('module_registry'),
            'plans' => Plan::query()->orderBy('monthly_price')->get(),
            'workspaces' => Workspace::query()->latest()->limit(20)->get(),
            'users' => User::query()->latest()->limit(20)->get(),
        ]);
    }

    public function store(
        UpsertFeatureFlagRequest $request,
        UpsertFeatureFlagAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $featureFlag = $action->handle($request->validated(), $request->user());

        return redirect()->route('admin.feature-flags.edit', $featureFlag)->with('status', $flash->created('الـ Feature Flag'));
    }

    public function edit(FeatureFlag $featureFlag): View
    {
        return view('admin.feature-flags.form', [
            'featureFlag' => $featureFlag->load('audiences'),
            'audiences' => $featureFlag->audiences,
            'method' => 'PUT',
            'action' => route('admin.feature-flags.update', $featureFlag),
            'modules' => config('module_registry'),
            'plans' => Plan::query()->orderBy('monthly_price')->get(),
            'workspaces' => Workspace::query()->latest()->limit(20)->get(),
            'users' => User::query()->latest()->limit(20)->get(),
        ]);
    }

    public function update(
        UpsertFeatureFlagRequest $request,
        FeatureFlag $featureFlag,
        UpsertFeatureFlagAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $featureFlag);

        return back()->with('status', $flash->updated('الـ Feature Flag'));
    }

    public function destroy(
        FeatureFlag $featureFlag,
        DeleteFeatureFlagAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $action->handle($featureFlag, request()->user());

        return redirect()->route('admin.feature-flags.index')->with('status', $flash->deleted('الـ Feature Flag'));
    }
}
