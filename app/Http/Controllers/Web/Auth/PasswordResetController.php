<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * عرض شاشة طلب رابط إعادة التعيين.
     */
    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * إرسال رابط إعادة التعيين دون كشف وجود البريد من عدمه.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'إن كان البريد مسجّلاً فستصلك رسالة لإعادة التعيين.');
    }

    /**
     * عرض شاشة تعيين كلمة مرور جديدة.
     */
    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * تنفيذ إعادة تعيين كلمة المرور عبر Password broker.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'تم تحديث كلمة المرور، سجّل الدخول.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
