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
use App\Support\Intelligence\SectorTemplateCatalog;
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
        SectorTemplateCatalog $sectorTemplateCatalog,
    ): View|RedirectResponse {
        $workspace = $this->currentWorkspace($request);

        if ($state->isCompleted($workspace)) {
            return redirect()->route('dashboard');
        }

        $profile = $profileStore->get($workspace);
        $defaultPersona = PersonaCatalog::exists($profile['persona'] ?? null)
            ? (string) $profile['persona']
            : PersonaCatalog::inferFromWorkspaceType($workspace->type);
        $defaultAwarenessLevel = AwarenessCatalog::exists($profile['awareness_level'] ?? null)
            ? (string) $profile['awareness_level']
            : PersonaCatalog::defaultAwareness($defaultPersona);
        $defaultPrimaryGoal = GoalCatalog::exists($profile['primary_goal'] ?? null)
            ? (string) $profile['primary_goal']
            : $this->defaultGoalForPersona($defaultPersona);

        return view('app.onboarding', [
            'workspace' => $workspace->load('account.owner'),
            'profile' => $profile,
            'personas' => PersonaCatalog::options(),
            'awarenessLevels' => AwarenessCatalog::options(),
            'goals' => GoalCatalog::options(),
            'sectorOptions' => $sectorTemplateCatalog->options(),
            'paths' => PathCatalog::options(),
            'contentLocales' => ContentLocaleCatalog::options(),
            'defaults' => [
                'persona' => $defaultPersona,
                'awareness_level' => $defaultAwarenessLevel,
                'primary_goal' => $defaultPrimaryGoal,
                'recommended_path' => PathCatalog::exists($profile['recommended_path'] ?? null)
                    ? (string) $profile['recommended_path']
                    : PathCatalog::recommend($defaultPersona, $defaultPrimaryGoal, $defaultAwarenessLevel),
                'audience' => (string) ($profile['audience'] ?? ''),
                'content_locale' => ContentLocaleCatalog::exists($profile['content_locale'] ?? null)
                    ? (string) $profile['content_locale']
                    : 'ar_modern_fusha',
            ],
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

    private function defaultGoalForPersona(string $persona): string
    {
        return match ($persona) {
            'freelancer' => 'build_offer',
            'business' => 'improve_marketing',
            'team', 'agency' => 'build_90_day_plan',
            default => 'clarify_idea',
        };
    }
}
