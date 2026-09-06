<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\CarriesStartIntent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Insights\ConversionRecorder;
use App\Services\Guests\GuestSessionManager;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    use CarriesStartIntent;

    public function __construct(
        private readonly ToolPresenter $presenter,
        private readonly GuestSessionManager $guests,
        private readonly ExperienceService $experiences,
        private readonly ConversionRecorder $conversions,
    ) {}

    public function create(Request $request): View
    {
        $tool = $this->rememberStartIntent($request);
        $experience = $this->rememberExperienceIntent($request);
        $this->rememberSafeReturnUrl($request);

        return view('auth.register', [
            'startTool' => $tool !== null ? $this->presenter->card($tool) : null,
            'startExperience' => $tool !== null ? Experience::BUSINESS : $experience,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $guest = $this->guests->current($request);
        $tool = $this->rememberStartIntent($request);
        $remembered = $this->rememberExperienceIntent($request);

        if (! $request->filled('experience') && ($guest !== null || $tool !== null)) {
            $request->merge(['experience' => Experience::BUSINESS->value]);
        } elseif (! $request->filled('experience') && $remembered !== null) {
            $request->merge(['experience' => $remembered->value]);
        }

        $data = $request->validate([
            'experience' => ['required', Rule::enum(Experience::class)],
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        /*
         * الحقول تُمرَّر مُسمّاة لا بتمرير `$data` كاملًا.
         *
         * `experience` ليس عمودًا في `users` ولا في `#[Fillable]`، فكان
         * يُهمَل بصمت. يعمل اليوم، وينقلب استثناءً على كل تسجيل لحظة
         * تفعيل `preventSilentlyDiscardingAttributes` — عطلٌ مؤجَّل في
         * أخطر مسار في المنصة، وتفكيكه أرخص من انتظاره.
         */
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $experience = Experience::from($data['experience']);
        $user = $this->experiences->selectInitial($user, $experience);

        event(new Registered($user));
        auth()->login($user);

        /*
         * التحويل يُسجَّل هنا لا في مسارات الخروج.
         *
         * الخروج من هذه الدالة خمسة مسارات مختلفة، ووضعه في كلٍّ منها
         * يجعل إضافة مسار سادس تُسقطه صامتًا. وهذا آخر موضع يقين فيه
         * أن الحساب أُنشئ فعلًا.
         */
        $this->conversions->record($request, 'signup', $experience->value);

        $request->session()->regenerate();

        // من جرّب كضيف: تنتقل مساحته وإجاباته ونتائجه إلى حسابه الجديد كما هي.
        if ($guest !== null) {
            $run = $guest->runs()->latest('id')->first();
            $this->guests->claim($guest, $user);
            $this->forgetStartIntent($request);

            if ($run !== null) {
                return redirect()->route('app.runs.review', $run)
                    ->with('status', __('حسابك جاهز، وكل ما جرّبته محفوظ في مشروعك.'));
            }

            return redirect()->route('app.dashboard');
        }

        $user->primaryWorkspace();

        if ($experience === Experience::LEARNING) {
            $this->forgetExperienceIntent($request);

            return redirect()->intended(route('app.learning.marketing.home'));
        }

        // من جاء من أداة محددة يكمل رحلته إليها، لا إلى لوحة فارغة.
        if ($tool !== null) {
            return redirect()->route('app.projects.create', ['tool' => $tool->key]);
        }

        $this->forgetExperienceIntent($request);

        return redirect()->intended(route('app.projects.create'));
    }
}
