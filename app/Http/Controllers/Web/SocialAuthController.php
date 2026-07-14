<?php

namespace App\Http\Controllers\Web;

use App\Application\Auth\EnsureUserWorkspaceAccessAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * تسجيل/دخول اجتماعي عبر الويب بجلسة Laravel (Google/Facebook/Twitter/LinkedIn).
 *
 * تدفّق ملائم للويب: المتصفح يفتح /auth/social/{provider} فيوجَّه إلى المزوّد،
 * وبعد الموافقة يعود إلى /auth/social/{provider}/callback فتُنشأ جلسة عبر
 * Auth::login (لا توكن ولا deep link — ذاك خاص بالموبايل).
 */
class SocialAuthController extends Controller
{
    /** خريطة اسم المزوّد العام → سائق Socialite. */
    private const DRIVERS = [
        'google' => 'google',
        'facebook' => 'facebook',
        'twitter' => 'twitter-oauth-2',
        'linkedin' => 'linkedin-openid',
    ];

    public function redirect(string $provider): RedirectResponse
    {
        $driver = $this->driverFor($provider);

        // بجلسة (بدون stateless) — الويب يملك session لحفظ حالة OAuth. نفرض مسار
        // callback الويب (لا الافتراضي في config الذي يشير لـ callback الموبايل/API).
        return Socialite::driver($driver)
            ->redirectUrl($this->webCallbackUrl($provider))
            ->redirect();
    }

    public function callback(
        string $provider,
        OnboardingState $state,
        EnsureUserWorkspaceAccessAction $ensureUserWorkspaceAccessAction,
    ): RedirectResponse {
        $driver = $this->driverFor($provider);

        try {
            // نفس مسار callback الويب المُستخدم في redirect (شرط مطابقة redirect_uri).
            $socialUser = Socialite::driver($driver)
                ->redirectUrl($this->webCallbackUrl($provider))
                ->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['email' => 'تعذّر تسجيل الدخول عبر المزوّد.']);
        }

        $user = $this->resolveUser($provider, $socialUser);

        $status = $user->status instanceof UserStatus
            ? $user->status->value
            : (string) $user->status;

        if ($status !== UserStatus::Active->value) {
            return redirect()->route('login')
                ->withErrors(['email' => 'تعذّر تسجيل الدخول عبر المزوّد.']);
        }

        Auth::login($user);
        session()->regenerate();

        $workspace = $ensureUserWorkspaceAccessAction->handle($user);
        session()->put('current_workspace_id', $workspace->id);

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route(
            $workspace && ! $state->isCompleted($workspace) ? 'onboarding.show' : 'dashboard'
        );
    }

    /** يجد المستخدم بالهوية الاجتماعية، أو يربط ببريد موجود، أو ينشئ مستخدماً جديداً. */
    private function resolveUser(string $provider, SocialiteUser $socialUser): User
    {
        $providerId = (string) $socialUser->getId();
        $email = $socialUser->getEmail();
        $email = is_string($email) && $email !== '' ? mb_strtolower(trim($email)) : null;

        // 1) بالهوية الاجتماعية نفسها.
        $byIdentity = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($byIdentity instanceof User) {
            $byIdentity->update([
                'avatar' => $socialUser->getAvatar() ?: $byIdentity->avatar,
                'last_login_at' => now(),
            ]);

            return $byIdentity;
        }

        // 2) ربط ببريد موجود (نفس الشخص سجّل سابقاً بكلمة مرور).
        if ($email !== null) {
            $byEmail = User::query()->where('email', $email)->first();

            if ($byEmail instanceof User) {
                $byEmail->update([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'avatar' => $socialUser->getAvatar() ?: $byEmail->avatar,
                    'last_login_at' => now(),
                ]);

                return $byEmail;
            }
        }

        // 3) مستخدم جديد بالكامل (بلا كلمة مرور).
        return DB::transaction(function () use ($provider, $providerId, $email, $socialUser): User {
            return User::query()->create([
                'name' => $socialUser->getName()
                    ?: $socialUser->getNickname()
                    ?: 'مستخدم '.ucfirst($provider),
                // بريد احتياطي فريد إن لم يوفّره المزوّد (تويتر قد لا يعيد بريداً).
                'email' => $email ?: $provider.'_'.$providerId.'@social.local',
                'password' => null,
                'locale' => 'ar',
                'status' => 'active',
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]);
        });
    }

    private function driverFor(string $provider): string
    {
        abort_unless(array_key_exists($provider, self::DRIVERS), 404, 'مزوّد غير مدعوم.');

        return self::DRIVERS[$provider];
    }

    /** مسار callback الخاص بالويب (بجلسة)، منفصل عن callback الموبايل (deep link). */
    private function webCallbackUrl(string $provider): string
    {
        return route('social.callback', ['provider' => $provider]);
    }
}
