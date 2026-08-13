<?php

namespace App\Modules\Shared\I18n;

use Illuminate\Http\Request;

/**
 * روابط الصفحة الواحدة بكل لغاتها — مصدر واحد لـ`canonical` و`hreflang`
 * وخريطة الموقع ومبدّل اللغة.
 *
 * ─── العطل الذي أوجد هذا الصنف ───
 *
 * كان `canonical` يُبنى بـ`url()->current()`، وهي تُسقط سلسلة الاستعلام.
 * فصفحة `/?lang=en` تُعلن أن نسختها القانونية هي `/` — أي **العربية**.
 * وفي الوقت نفسه كان `hreflang` يقول لجوجل إن `/?lang=en` هي النسخة
 * الإنجليزية.
 *
 * الإشارتان متناقضتان: واحدة تقول «هذه صفحة إنجليزية مستقلة» والأخرى تقول
 * «هذه نسخة مكررة من العربية، لا تفهرسها». وجوجل يحسم التناقض لصالح
 * `canonical` دائمًا — فتسقط الإنجليزية والفرنسية من الفهرس كلتاهما، وتبقى
 * الترجمة كلها غير مرئية في البحث مهما بلغت جودتها.
 *
 * ولا يظهر هذا في أي اختبار ولا في تصفّح الموقع: الصفحات تعمل، والزائر
 * يراها، ولا شيء يُخطئ. يظهر بعد أشهر في «لماذا لا تجلب اللغات حركة؟».
 */
final class LocaleUrls
{
    public function __construct(private readonly LocaleRegistry $locales) {}

    /**
     * رابط الصفحة الحالية بلغة بعينها.
     *
     * لغة المصدر بلا معامل: هي الافتراضي، وإضافة `?lang=ar` تُنشئ عنوانًا
     * ثانيًا لنفس المحتوى — وهو ما نحاول تفاديه لا صنعه.
     */
    public function forLocale(string $locale, ?Request $request = null): string
    {
        $request ??= request();

        // التنظيف قبل إضافة اللغة لا بعدها: `fullUrlWithQuery` تحتفظ
        // بـ`utm_*` كما هي، فيصير لكل حملة رابطٌ قانونيّ مستقلّ — وهو
        // تفتيتٌ للإشارات لا توحيد لها.
        return $this->absolute($this->stripped($request), $locale);
    }

    /**
     * الرابط القانوني للصفحة كما تُعرض الآن — بلغتها لا بلغة أخرى.
     */
    public function canonical(?Request $request = null): string
    {
        return $this->forLocale(app()->getLocale(), $request);
    }

    /**
     * كل اللغات المفعّلة وروابطها لهذه الصفحة.
     *
     * @return array<string, string> رمز اللغة ← الرابط
     */
    public function alternates(?Request $request = null): array
    {
        $out = [];

        foreach ($this->locales->enabled() as $code) {
            $out[$code] = $this->forLocale($code, $request);
        }

        return $out;
    }

    /**
     * رابط بلغة بعينها لمسار مُعطى — لخريطة الموقع، حيث لا طلب جاريًا.
     */
    public function absolute(string $url, string $locale): string
    {
        if ($locale === $this->locales->source()) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'lang='.$locale;
    }

    /**
     * الرابط بلا معامل اللغة ولا معاملات التتبّع.
     *
     * `utm_*` و`fbclid` تصنع عنوانًا جديدًا لكل حملة، فيرى جوجل عشرات
     * النسخ من صفحة واحدة. إسقاطها من `canonical` يجمع إشاراتها كلها على
     * عنوان واحد بدل تفتيتها.
     */
    private function stripped(Request $request): string
    {
        $query = $request->query();

        foreach (array_keys($query) as $key) {
            if ($key === 'lang' || str_starts_with((string) $key, 'utm_') || in_array($key, ['fbclid', 'gclid', 'msclkid', 'ref'], true)) {
                unset($query[$key]);
            }
        }

        $base = $request->url();

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }
}
