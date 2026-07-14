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

        // علامة المصدر (ويب) في الجلسة — يقرأها الـ callback الموحّد ليتفرّع. الويب
        // بجلسة (state/PKCE مفعّلان)، ونفرض مسار callback الموحّد.
        session()->put('social_origin', 'web');

        return Socialite::driver($driver)
            ->redirectUrl($this->webCallbackUrl($provider))
            ->redirect();
    }

    /**
     * Callback موحّد لكل من الويب والموبايل. يكتشف المصدر عبر علامة الجلسة:
     * - ويب (علامة موجودة، بجلسة/state): Auth::login + توجيه لصفحة.
     * - موبايل (لا علامة، stateless): توكن Sanctum + deep link ksgrowth://.
     */
    public function callback(
        string $provider,
        OnboardingState $state,
        EnsureUserWorkspaceAccessAction $ensureUserWorkspaceAccessAction,
    ): RedirectResponse {
        $driver = $this->driverFor($provider);
        $isWeb = session()->pull('social_origin') === 'web';

        try {
            $flow = Socialite::driver($driver)->redirectUrl($this->webCallbackUrl($provider));
            // الموبايل stateless (لا جلسة عبر متصفح الجهاز)؛ الويب stateful (CSRF/PKCE).
            $socialUser = ($isWeb ? $flow : $flow->stateless())->user();
        } catch (\Throwable $e) {
            return $isWeb
                ? redirect()->route('login')->withErrors(['email' => 'تعذّر تسجيل الدخول عبر المزوّد.'])
                : redirect($this->deepLink(['error' => 'social_auth_failed']));
        }

        $user = $this->resolveUser($provider, $socialUser);

        $status = $user->status instanceof UserStatus
            ? $user->status->value
            : (string) $user->status;

        if ($status !== UserStatus::Active->value) {
            return $isWeb
                ? redirect()->route('login')->withErrors(['email' => 'الحساب غير نشط.'])
                : redirect($this->deepLink(['error' => 'account_frozen']));
        }

        $workspace = $ensureUserWorkspaceAccessAction->handle($user);
        $user->forceFill(['last_login_at' => now()])->save();

        // مسار الموبايل: توكن + deep link.
        if (! $isWeb) {
            $token = $user->createToken(
                $provider.'-mobile',
                ['workspace:'.$workspace->public_id],
            )->plainTextToken;

            return redirect($this->deepLink([
                'token' => $token,
                'workspace' => $workspace->public_id,
            ]));
        }

        // مسار الويب: جلسة Laravel.
        Auth::login($user);
        session()->regenerate();
        session()->put('current_workspace_id', $workspace->id);

        return redirect()->route(
            ! $state->isCompleted($workspace) ? 'onboarding.show' : 'dashboard'
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

    /** مسار الـ callback الموحّد (يخدم الويب والموبايل معاً). */
    private function webCallbackUrl(string $provider): string
    {
        return route('social.callback', ['provider' => $provider]);
    }

    /** رابط العودة العميق للتطبيق (مسار الموبايل داخل الـ callback الموحّد). */
    private function deepLink(array $params): string
    {
        return 'ksgrowth://auth/social?'.http_build_query($params);
    }
}
