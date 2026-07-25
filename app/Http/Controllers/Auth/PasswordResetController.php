<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * الطريق الوحيد للعائد الذي نسي كلمة مروره.
 *
 * قبل هذا كان يخرج من المنتج نهائيًا: لا رابط، ولا مسار، ولا رسالة.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // لا نكشف إن كان البريد مسجلًا أم لا: الرسالة واحدة في الحالتين.
        return back()->with('status', 'إذا كان البريد مسجلًا، فستصلك رسالة تحتوي على رابط لتغيير كلمة المرور. تفقّد صندوق الوارد خلال دقائق.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'الرابط انتهت صلاحيته أو استُخدم من قبل. اطلب رابطًا جديدًا.']);
        }

        return redirect()->route('login')->with('status', 'تغيّرت كلمة المرور. يمكنك تسجيل الدخول الآن.');
    }
}
