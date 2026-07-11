<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\ApiException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController
{
    /**
     * إرسال رابط/رمز إعادة تعيين كلمة المرور إلى البريد.
     */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // لا نكشف ما إذا كان البريد مسجّلاً (منعاً لتعداد الحسابات).
        return response()->json([
            'data' => [
                'sent' => in_array($status, [Password::RESET_LINK_SENT], true),
                'message' => 'إن كان البريد مسجّلاً فستصلك رسالة لإعادة التعيين.',
            ],
        ]);
    }

    /**
     * إعادة تعيين كلمة المرور باستخدام الرمز المُرسَل.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException(
                __($status),
                'PASSWORD_RESET_FAILED',
                422,
                ['email' => [__($status)]],
            );
        }

        return response()->json([
            'data' => ['message' => 'تم تغيير كلمة المرور بنجاح.'],
        ]);
    }
}
