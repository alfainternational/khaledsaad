<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\CarriesStartIntent;
use App\Http\Controllers\Controller;
use App\Modules\Insights\ConversionRecorder;
use App\Notifications\LoginOtpNotification;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use CarriesStartIntent;

    public function __construct(
        private readonly ToolPresenter $presenter,
        private readonly ConversionRecorder $conversions,
    ) {}

    public function create(Request $request): View
    {
        $tool = $this->rememberStartIntent($request);
        $this->rememberExperienceIntent($request);
        $this->rememberSafeReturnUrl($request);

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
                'email' => __('بيانات الدخول غير صحيحة.'),
            ]);
        }

        // خطوة التحقق الثانية (بند ٢٣): من فعّلها لا يدخل بكلمة المرور وحدها.
        // نوقف الجلسة، نرسل رمزًا قصير العمر بالبريد، ونحوّله لشاشة الرمز.
        $user = auth()->user();

        if ($user->two_factor_email_enabled) {
            auth()->logout();

            $code = (string) random_int(100000, 999999);
            cache()->put('login-otp:'.$user->id, Hash::make($code), now()->addMinutes(10));
            $user->notify(new LoginOtpNotification($code));

            $request->session()->put('otp_user_id', $user->id);
            $request->session()->put('otp_remember', $request->boolean('remember'));

            return redirect()->route('login.otp');
        }

        // الدخول الناجح تحويل: من عاد بنفسه أهمّ إشارة بقاء لدينا،
        // وبدونه تبدو المنصة بلا مستخدمين حتى وهم يدخلونها يوميًّا.
        $this->conversions->record($request, 'login');

        $request->session()->regenerate();
        $this->forgetExperienceIntent($request);

        // العائد الذي جاء من أداة يُنقل إليها مباشرة ليختار المشروع ويشغّلها.
        $tool = $this->consumeStartIntent($request);

        if ($tool !== null) {
            return redirect()->route('app.tools.show', $tool->key);
        }

        return redirect()->intended(route('app.dashboard'));
    }

    public function otpForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('otp_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.otp');
    }

    public function otpVerify(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|digits:6']);

        $userId = $request->session()->get('otp_user_id');
        $hashed = $userId ? cache()->get('login-otp:'.$userId) : null;

        if ($userId === null || $hashed === null || ! Hash::check($request->string('code'), $hashed)) {
            throw ValidationException::withMessages([
                'code' => __('الرمز غير صحيح أو انتهت صلاحيته — اطلب الدخول من جديد.'),
            ]);
        }

        // رمز يُستخدم مرة واحدة.
        cache()->forget('login-otp:'.$userId);

        auth()->loginUsingId($userId, (bool) $request->session()->pull('otp_remember', false));

        // من دخل بخطوتين دخل فعلًا: إغفاله هنا كان سيجعل التحقق الثنائي
        // يبدو كأنه يُوقف المستخدمين، بينما هو يمرّرهم من باب آخر.
        $this->conversions->record($request, 'login', 'otp');

        $request->session()->forget('otp_user_id');
        $request->session()->regenerate();
        $this->forgetExperienceIntent($request);

        $tool = $this->consumeStartIntent($request);

        return $tool !== null
            ? redirect()->route('app.tools.show', $tool->key)
            : redirect()->intended(route('app.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
