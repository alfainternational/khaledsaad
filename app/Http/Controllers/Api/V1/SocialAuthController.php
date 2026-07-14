<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\EnsureUserWorkspaceAccessAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * تسجيل/دخول اجتماعي عبر Google/Facebook/Twitter/LinkedIn.
 *
 * تدفّق ملائم للموبايل: التطبيق يفتح /redirect في المتصفح، وبعد موافقة المزوّد
 * يعود إلى /callback، فيُصدر توكن Sanctum ويعيد التوجيه إلى deep link التطبيق
 * (ksgrowth://auth/social?token=...&workspace=...) — نفس بنية عودة الفوترة.
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

        return Socialite::driver($driver)->stateless()->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $driver = $this->driverFor($provider);

        try {
            $socialUser = Socialite::driver($driver)->stateless()->user();
        } catch (\Throwable $e) {
            return redirect($this->deepLink(['error' => 'social_auth_failed']));
        }

        $user = $this->resolveUser($provider, $socialUser);

        if ($user->status?->value === 'frozen' || $user->status === 'frozen') {
            return redirect($this->deepLink(['error' => 'account_frozen']));
        }

        $workspace = app(EnsureUserWorkspaceAccessAction::class)->handle($user);

        $token = $user->createToken(
            $provider.'-mobile',
            ['workspace:'.$workspace->public_id],
        )->plainTextToken;

        return redirect($this->deepLink([
            'token' => $token,
            'workspace' => $workspace->public_id,
        ]));
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

    /** يبني رابط العودة العميق للتطبيق مع معطيات الاستعلام. */
    private function deepLink(array $params): string
    {
        return 'ksgrowth://auth/social?'.http_build_query($params);
    }
}
