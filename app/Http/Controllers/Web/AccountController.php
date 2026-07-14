<?php

namespace App\Http\Controllers\Web;

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\UpdateAccountSettingsRequest;
use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use App\Support\PlatformSectionCatalog;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function edit(
        Request $request,
        EntitlementResolver $resolver,
        WorkspaceProfileStore $profileStore,
    ): View {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('account'));
        }

        $workspace = $this->currentWorkspace($request);
        $account = $workspace->account()->with('subscription.plan', 'members.user')->firstOrFail();

        return view('app.account', [
            'workspace' => $workspace,
            'account' => $account,
            'isAccountOwner' => $request->user()->id === $account->owner_user_id,
            'aiKeyConnected' => $account->hasByoAi(),
            'aiKeyProvider' => $account->ai_provider,
            'aiKeyMasked' => $account->hasByoAi()
                ? str_repeat('•', 8).substr($account->ai_provider_key, -4)
                : null,
            'aiKeyProviders' => AccountAiKeyController::PROVIDERS,
            'members' => $workspace->members()->with('user')->get(),
            'invitations' => $workspace->invitations()->latest()->get(),
            'profile' => $profileStore->get($workspace),
            'personas' => PersonaCatalog::options(),
            'awarenessLevels' => AwarenessCatalog::options(),
            'goals' => GoalCatalog::options(),
            'paths' => PathCatalog::options(),
            'contentLocales' => ContentLocaleCatalog::options(),
            'entitlements' => $account->subscription?->plan
                ? $resolver->allForPlan($account->subscription->plan)
                : [],
        ]);
    }

    public function update(
        UpdateAccountSettingsRequest $request,
        WorkspaceProfileStore $profileStore,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $account = $workspace->account()->firstOrFail();
        $user = $request->user();
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'locale' => $data['locale'],
        ]);

        $account->update([
            'name' => $data['account_name'],
            'billing_email' => $data['billing_email'],
        ]);

        $workspace->update([
            'name' => $data['workspace_name'],
            'type' => $data['workspace_type'],
        ]);

        $profileStore->put($workspace, [
            'persona' => $data['persona'],
            'awareness_level' => $data['awareness_level'],
            'primary_goal' => $data['primary_goal'],
            'recommended_path' => $data['recommended_path'] ?? null,
            'audience' => $data['audience'],
            'country' => $data['country'],
            'content_locale' => $data['content_locale'],
            'current_challenge' => $data['current_challenge'] ?? null,
        ]);

        return redirect()->route('account.index')->with('status', $flash->updated('إعدادات الحساب ومساحة العمل'));
    }
}
