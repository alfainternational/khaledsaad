<?php

namespace App\Http\Controllers\Web;

use App\Application\Workspace\CompleteWorkspaceOnboardingAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\CompleteOnboardingRequest;
use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function show(
        Request $request,
        OnboardingState $state,
        WorkspaceProfileStore $profileStore,
    ): View|RedirectResponse {
        $workspace = $this->currentWorkspace($request);

        if ($state->isCompleted($workspace)) {
            return redirect()->route('dashboard');
        }

        return view('app.onboarding', [
            'workspace' => $workspace->load('account.owner'),
            'profile' => $profileStore->get($workspace),
            'personas' => PersonaCatalog::options(),
            'awarenessLevels' => AwarenessCatalog::options(),
            'goals' => GoalCatalog::options(),
            'paths' => PathCatalog::options(),
            'contentLocales' => ContentLocaleCatalog::options(),
        ]);
    }

    public function store(
        CompleteOnboardingRequest $request,
        CompleteWorkspaceOnboardingAction $action,
        OnboardingState $state,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $account = $workspace->account()->firstOrFail();
        $user = $request->user();
        $data = $request->validated();

        $action->handle($workspace, $account, $user, $data, $state);

        return redirect()->route('dashboard')->with('status', $flash->onboardingCompleted());
    }
}
