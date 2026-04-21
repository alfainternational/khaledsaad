<?php

namespace App\Http\Controllers\Web;

use App\Application\Auth\EnsureUserWorkspaceAccessAction;
use App\Application\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Enums\UserStatus;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(
        LoginRequest $request,
        OnboardingState $state,
        EnsureUserWorkspaceAccessAction $ensureUserWorkspaceAccessAction,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, false)) {
            return back()->withErrors(['email' => $flash->invalidCredentials()])->onlyInput('email');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        $status = $user->status instanceof UserStatus
            ? $user->status->value
            : (string) $user->status;

        if ($status !== UserStatus::Active->value) {
            Auth::logout();

            return back()->withErrors(['email' => $flash->inactiveAccount()])->onlyInput('email');
        }

        $workspace = $ensureUserWorkspaceAccessAction->handle($user);
        $request->session()->put('current_workspace_id', $workspace->id);

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route($workspace && ! $state->isCompleted($workspace) ? 'onboarding.show' : 'dashboard');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function register(
        RegisterRequest $request,
        RegisterUserAction $action,
        EnsureUserWorkspaceAccessAction $ensureUserWorkspaceAccessAction,
    ): RedirectResponse {
        $user = $action->handle($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        $workspace = $ensureUserWorkspaceAccessAction->handle($user);
        $request->session()->put('current_workspace_id', $workspace->id);

        return redirect()->route('onboarding.show');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }
}
