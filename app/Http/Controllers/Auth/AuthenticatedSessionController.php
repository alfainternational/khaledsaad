<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\CarriesStartIntent;
use App\Http\Controllers\Controller;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use CarriesStartIntent;

    public function __construct(private readonly ToolPresenter $presenter) {}

    public function create(Request $request): View
    {
        $tool = $this->rememberStartIntent($request);

        return view('auth.login', [
            'startTool' => $tool !== null ? $this->presenter->card($tool) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! auth()->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        $request->session()->regenerate();

        // العائد الذي جاء من أداة يُنقل إليها مباشرة ليختار المشروع ويشغّلها.
        $tool = $this->consumeStartIntent($request);

        if ($tool !== null) {
            return redirect()->route('app.tools.show', $tool->key);
        }

        return redirect()->intended(route('app.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
