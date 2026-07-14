<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Auth\SocialProviderCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

/**
 * بدء تسجيل الدخول الاجتماعي للموبايل. التطبيق يفتح هذا المسار في المتصفح، وبعد
 * موافقة المزوّد يعود إلى الـ callback الموحّد (Web\SocialAuthController@callback)
 * الذي يكتشف أنه موبايل (لا علامة جلسة) فيُصدر توكن Sanctum ويعيد التوجيه إلى
 * deep link التطبيق (ksgrowth://auth/social?token=...). لا نكرّر منطق الـ callback.
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

    /** المزوّدون المفعّلون (لإظهار المفعّل فقط في شاشة الدخول). عام بلا مصادقة. */
    public function providers(): JsonResponse
    {
        return response()->json(['data' => SocialProviderCatalog::enabled()]);
    }

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(array_key_exists($provider, self::DRIVERS), 404, 'مزوّد غير مدعوم.');

        // يوجّه للـ callback الموحّد؛ لأنه stateless وبلا علامة جلسة social_origin،
        // يعامله الـ callback كموبايل (توكن + deep link).
        return Socialite::driver(self::DRIVERS[$provider])
            ->stateless()
            ->redirectUrl(route('social.callback', ['provider' => $provider]))
            ->redirect();
    }
}
