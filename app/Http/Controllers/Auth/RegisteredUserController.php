<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\CarriesStartIntent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Guests\GuestSessionManager;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    use CarriesStartIntent;

    public function __construct(
        private readonly ToolPresenter $presenter,
        private readonly GuestSessionManager $guests,
    ) {}

    public function create(Request $request): View
    {
        $tool = $this->rememberStartIntent($request);

        return view('auth.register', [
            'startTool' => $tool !== null ? $this->presenter->card($tool) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data);

        event(new Registered($user));
        auth()->login($user);
        $request->session()->regenerate();

        // من جرّب كضيف: تنتقل مساحته وإجاباته ونتائجه إلى حسابه الجديد كما هي.
        $guest = $this->guests->current($request);

        if ($guest !== null) {
            $run = $guest->runs()->latest('id')->first();
            $this->guests->claim($guest, $user);
            $this->forgetStartIntent($request);

            if ($run !== null) {
                return redirect()->route('app.runs.review', $run)
                    ->with('status', 'حسابك جاهز، وكل ما جرّبته محفوظ في مشروعك.');
            }

            return redirect()->route('app.dashboard');
        }

        // من جاء من أداة محددة يكمل رحلته إليها، لا إلى لوحة فارغة.
        $tool = $this->rememberStartIntent($request);

        if ($tool !== null) {
            return redirect()->route('app.projects.create', ['tool' => $tool->key]);
        }

        return redirect()->intended(route('app.dashboard'));
    }
}
