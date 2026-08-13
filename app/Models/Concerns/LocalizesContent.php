<?php

namespace App\Models\Concerns;

use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تركيب الترجمة على المحتوى وقت العرض.
 *
 * سبب وجود السمة في ملف مستقل لا داخل `Content`: النموذج يُعدَّل من عدة
 * جهات، وحقن أربعين سطرًا في وسطه يجعل كل تعديل لاحق يتنازع معه. السمة
 * تجعل أثر هذه القدرة على النموذج سطرًا واحدًا.
 *
 * `localize()` تكتب فوق سمات النموذج في الذاكرة ولا تحفظ. هذا مقصود:
 * القوالب تقرأ `$content->title` و`$content->body_html` في عشرات المواضع،
 * وتغييرها كلها إلى `$content->translated('title')` عملٌ واسع يُنسى منه
 * موضع فيظهر عربيًّا وسط صفحة إنجليزية. الكتابة فوق السمات تجعل كل قالب
 * يعمل بلا تعديل. ولأنها تلمس الحالة، تُستدعى في طبقة العرض وحدها —
 * ولا يجوز استدعاؤها قبل `save()` أبدًا.
 */
trait LocalizesContent
{
    private ?string $resolvedDisplayLocale = null;

    private bool $localeApplied = false;

    private bool $translationStale = false;

    public function translations(): HasMany
    {
        return $this->hasMany(ContentTranslation::class);
    }

    /**
     * لغة المادة الأصلية: عمود `locale` إن وُجد، وإلا لغة مصدر المشروع.
     */
    public function sourceLocale(): string
    {
        $locale = $this->getAttribute('locale');

        return is_string($locale) && $locale !== ''
            ? $locale
            : (string) config('locales.source', 'ar');
    }

    /**
     * اللغة التي يُعرض بها هذا المحتوى فعلًا بعد `localize()`.
     *
     * تُقرأ في القالب لضبط `lang` و`dir` على كتلة المقال وحدها: صفحة
     * إنجليزية تعرض درسًا عربيًّا يجب أن تعلن عربية الكتلة، وإلا قرأها
     * قارئ الشاشة بلفظ إنجليزي وعرضها المتصفح باتجاه خاطئ.
     */
    public function displayLocale(): string
    {
        return $this->resolvedDisplayLocale ?? $this->sourceLocale();
    }

    public function isTranslated(): bool
    {
        return $this->localeApplied;
    }

    /**
     * عُرضت ترجمة، لكن الأصل العربي تغيّر بعدها.
     */
    public function hasStaleTranslation(): bool
    {
        return $this->translationStale;
    }

    /**
     * ركّب ترجمة اللغة المطلوبة إن وُجدت.
     *
     * الغياب ليس خطأ ولا يُملأ بتقدير: المحتوى يبقى بلغته الأصلية،
     * و`isTranslated()` تُرجع false ليعلن القالبُ الفجوةَ صراحةً (§٤.٣).
     */
    public function localize(?string $locale = null): static
    {
        $locale ??= app()->getLocale();
        $this->resolvedDisplayLocale = $this->sourceLocale();

        if ($locale === $this->sourceLocale()) {
            return $this;
        }

        $translation = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();

        if (! $translation instanceof ContentTranslation) {
            return $this;
        }

        $this->translationStale = $translation->isStaleAgainst((string) $this->getAttribute('source_text_hash'));

        /*
         * التقادم لا يُخفي الترجمة بل يُعرض معها وسمًا. إخفاؤها يعيد
         * القارئ إلى العربية بلا تفسير، وهو أسوأ من نصّ مترجم متأخّر
         * عن تعديل في أصله.
         */
        foreach (['title', 'excerpt', 'body_html', 'body_json', 'seo_title', 'seo_description'] as $field) {
            $value = $translation->getAttribute($field);

            if ($value !== null && $value !== '') {
                $this->setAttribute($field, $value);
            }
        }

        $this->resolvedDisplayLocale = $locale;
        $this->localeApplied = true;

        // لا تُحسب السمات المكتوبة تعديلًا: حفظًا عرضيًّا يخبز الترجمة
        // في صفّ الأصل فيضيع النص العربي بلا رجعة.
        $this->syncOriginal();

        return $this;
    }
}
