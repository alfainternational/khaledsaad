<?php

namespace App\Modules\Shared\I18n;

/**
 * قارئ سجل اللغات: الواجهة الوحيدة نحو `config/locales.php`.
 *
 * سبب وجوده: القوالب تحتاج اتجاه الكتابة وعائلة الخط، والمترجم يحتاج
 * النبرة، والوسيط يحتاج قائمة المدعوم. قراءة `config()` مباشرة من كل
 * موضع تنشر معرفة بنية الملف في المشروع كله، فأي تغيير في شكلها يصير
 * بحثًا نصيًّا لا تعديلًا في صنف واحد.
 */
final class LocaleRegistry
{
    /**
     * لغة المصدر: التي تُكتب بها القوالب، ولا يوجد لها ملف ترجمة لأن
     * المفتاح نفسه هو نصها.
     */
    public function source(): string
    {
        return (string) config('locales.source', 'ar');
    }

    /**
     * اللغات المفعّلة، مرتبةً كما وردت في الإعداد ومصفّاةً على المدعوم
     * فعلًا: رمزٌ في `enabled` بلا تعريف في `supported` خطأ إعداد صامت.
     *
     * @return array<int, string>
     */
    public function enabled(): array
    {
        $supported = (array) config('locales.supported', []);

        return array_values(array_filter(
            (array) config('locales.enabled', []),
            fn ($code) => is_string($code) && isset($supported[$code]),
        ));
    }

    /**
     * اللغات المفعّلة عدا لغة المصدر: هي وحدها التي تحتاج ملف ترجمة.
     *
     * @return array<int, string>
     */
    public function targets(): array
    {
        return array_values(array_diff($this->enabled(), [$this->source()]));
    }

    public function isEnabled(?string $code): bool
    {
        return $code !== null && in_array($code, $this->enabled(), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(?string $code = null): array
    {
        $code ??= app()->getLocale();
        $all = (array) config('locales.supported', []);

        return (array) ($all[$code] ?? $all[$this->source()] ?? []);
    }

    public function direction(?string $code = null): string
    {
        return (string) ($this->meta($code)['dir'] ?? 'rtl');
    }

    public function isRtl(?string $code = null): bool
    {
        return $this->direction($code) === 'rtl';
    }

    public function htmlLang(?string $code = null): string
    {
        return (string) ($this->meta($code)['html'] ?? $code ?? $this->source());
    }

    public function ogLocale(?string $code = null): string
    {
        return (string) ($this->meta($code)['og'] ?? 'ar_SA');
    }

    public function fontFamily(?string $code = null): string
    {
        return (string) ($this->meta($code)['font'] ?? 'arabic');
    }

    public function nativeName(?string $code = null): string
    {
        return (string) ($this->meta($code)['native'] ?? (string) $code);
    }

    public function englishName(?string $code = null): string
    {
        return (string) ($this->meta($code)['english'] ?? (string) $code);
    }

    public function tone(?string $code = null): string
    {
        return (string) ($this->meta($code)['tone'] ?? '');
    }

    /**
     * نبرة **التوليد** — كيف يُكتب تقرير من الصفر، لا كيف يُنقل نصّ قصير.
     *
     * الفصل عن `tone()` مقصود: نبرة الترجمة تصف نقل تسمية زر أو عنوان عمود
     * («Imperative mood for buttons»)، وتطبيقها على تقرير من ألف كلمة يُنتج
     * نصًّا مقتضبًا بلا شرح. والعكس أسوأ: نبرة التقرير على زر تُنتج جملة.
     */
    public function generationTone(?string $code = null): string
    {
        return (string) ($this->meta($code)['generation'] ?? $this->tone($code));
    }

    /**
     * هل النصّ كلّه أسماء «لا تُترجَم» وعلامات ترقيم؟
     *
     * سبب وجوده: مخرَجٌ مطابق للأصل يعني عادةً أن النموذج نسخ ولم يترجم،
     * وهو عطل. إلا في نصّ كلّه اسمُ علامة أو منصّة — «— خالد سعد» — فالتطابق
     * فيه هو الصواب. بلا هذا التمييز يصير اسم العلامة نفسه نصًّا «فاشلًا»
     * في كل لغة تُضاف، فيُبلَّغ عنه إلى الأبد ولا يُصلَح لأنه ليس عطلًا.
     */
    public function isProtectedName(string $text): bool
    {
        $remainder = $text;

        foreach ((array) config('locales.glossary.keep', []) as $name) {
            $remainder = str_replace((string) $name, '', $remainder);
        }

        return preg_replace('/[\p{P}\p{S}\p{Z}\s]+/u', '', $remainder) === '';
    }

    /**
     * صفوف مبدّل اللغة: كل ما تحتاجه الواجهة لرسمه دون معرفة الإعداد.
     *
     * @return array<int, array{code: string, native: string, english: string, dir: string, current: bool}>
     */
    public function switcher(?string $current = null): array
    {
        $current ??= app()->getLocale();

        return array_map(fn (string $code): array => [
            'code' => $code,
            'native' => $this->nativeName($code),
            'english' => $this->englishName($code),
            'dir' => $this->direction($code),
            'current' => $code === $current,
        ], $this->enabled());
    }
}
