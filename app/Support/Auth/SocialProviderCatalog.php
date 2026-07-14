<?php

namespace App\Support\Auth;

/**
 * مصدر الحقيقة الواحد للمزوّدين الاجتماعيين المفعّلين.
 * المزوّد «مفعّل» فقط عند ضبط client_id و client_secret له (من .env أو لوحة الآدمن).
 * تستهلكه واجهات الويب والتطبيق لإظهار المفعّل فقط.
 */
final class SocialProviderCatalog
{
    /** المفتاح العام → [التسمية, سائق Socialite في config]. */
    public const PROVIDERS = [
        'google' => ['label' => 'Google', 'driver' => 'google'],
        'facebook' => ['label' => 'Facebook', 'driver' => 'facebook'],
        'twitter' => ['label' => 'X', 'driver' => 'twitter-oauth-2'],
        'linkedin' => ['label' => 'LinkedIn', 'driver' => 'linkedin-openid'],
    ];

    /**
     * مفاتيح المزوّدين المفعّلين (لهم client_id و client_secret).
     *
     * @return array<int, string>
     */
    public static function enabled(): array
    {
        $enabled = [];

        foreach (self::PROVIDERS as $key => $meta) {
            $driver = $meta['driver'];
            if (filled(config("services.{$driver}.client_id"))
                && filled(config("services.{$driver}.client_secret"))) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }
}
