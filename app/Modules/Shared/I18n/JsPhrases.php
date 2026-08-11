<?php

namespace App\Modules\Shared\I18n;

use Illuminate\Support\Facades\File;

/**
 * القاموس الذي يصل إلى المتصفح.
 *
 * حزمة JavaScript واحدة تُخدَم لكل اللغات — بناء حزمة لكل لغة يضاعف
 * المخرَج ويُبطل كاش المتصفح عند كل تبديل لغة. فالنصوص تصل من القالب.
 *
 * والمرسَل هو مفاتيح JS وحدها لا الكتالوج كاملًا: الفرق ~٩٠ نصًّا مقابل
 * ٣٣٠٠، أي كيلوبايتان بدل مئة على كل طلب صفحة.
 *
 * لغة المصدر لا تحتاج قاموسًا إطلاقًا: المفتاح هو النصّ العربي، و`t()`
 * تُرجع المفتاح حين لا تجده. فتُرسَل `{}` ولا يُقرأ ملفٌ ولا يُشغَل كاش.
 */
final class JsPhrases
{
    /** @var array<string, array<string, string>> اللغة ← القاموس */
    private static array $memo = [];

    /**
     * @return array<string, string>
     */
    public function forLocale(string $locale): array
    {
        if (isset(self::$memo[$locale])) {
            return self::$memo[$locale];
        }

        if ($locale === (string) config('locales.source', 'ar')) {
            return self::$memo[$locale] = [];
        }

        $path = base_path((string) config('locales.scan.js.keys', 'lang/_source/js-keys.json'));

        if (! File::exists($path)) {
            return self::$memo[$locale] = [];
        }

        $keys = json_decode((string) File::get($path), true);

        if (! is_array($keys)) {
            return self::$memo[$locale] = [];
        }

        $phrases = [];

        foreach ($keys as $key) {
            if (! is_string($key)) {
                continue;
            }

            $translated = trans($key, [], $locale);

            /*
             * النصّ غير المترجَم يُترَك خارج القاموس لا يُرسَل مطابقًا
             * لمفتاحه: `t()` تُرجع المفتاح أصلًا حين لا تجده، فإرساله
             * تكرارٌ خالص على كل طلب.
             */
            if (is_string($translated) && $translated !== $key) {
                $phrases[$key] = $translated;
            }
        }

        return self::$memo[$locale] = $phrases;
    }

    /**
     * تفريغ الذاكرة — للاختبارات التي تبدّل اللغة داخل الطلب الواحد.
     */
    public static function forget(): void
    {
        self::$memo = [];
    }
}
