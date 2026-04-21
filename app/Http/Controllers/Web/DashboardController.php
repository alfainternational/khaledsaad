<?php

namespace App\Http\Controllers\Web;

use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Dashboard\DashboardResolver;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function __invoke(
        Request $request,
        OnboardingState $state,
        DashboardResolver $dashboardResolver,
    ): View|RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $workspace->loadMissing('account.subscription.plan');

        if (! $state->isCompleted($workspace)) {
            return redirect()->route('onboarding.show');
        }

        $dashboard = $dashboardResolver->resolve($workspace, $request->user());

        return view('app.dashboard', [
            'workspace' => $workspace,
            'availableWorkspaces' => $request->user()->activeWorkspaces()->with('account.subscription.plan')->get(),
            'dashboard' => $dashboard,
        ]);
    }

    public function switchWorkspace(Workspace $workspace, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('switch', $workspace);

        request()->session()->put('current_workspace_id', $workspace->id);

        return back()->with('status', $flash->switchedWorkspace());
    }
}
