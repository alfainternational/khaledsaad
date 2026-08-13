<?php

namespace App\Modules\Shared\I18n;

use Illuminate\Support\Facades\File;

/**
 * كتالوج الترجمة على القرص: النصوص المستخرَجة، والترجمات المخبوزة،
 * ومصدر كل ترجمة.
 *
 * لماذا ملفات في المستودع لا جدول في قاعدة البيانات؟
 *
 * لأن الترجمة جزء من الإصدار لا من البيانات. النص الذي يراه المستخدم
 * يجب أن يكون مراجَعًا ومثبَّتًا في نفس اللحظة التي يُثبَّت فيها الكود
 * الذي يعرضه. ولو كانت في قاعدة البيانات لصار سؤال «لماذا تغيّر هذا
 * السطر؟» بلا جواب، ولاحتاج كل نشر بذرًا جديدًا. الملف يجيب بـ
 * `git log`، ويُنشر مع الكود، ولا يكلّف استعلامًا واحدًا وقت الطلب.
 *
 * مفتاح كل نص هو النص العربي نفسه: لا مفاتيح مخترعة. فائدته أن اللغة
 * العربية تعمل بلا ملف ترجمة إطلاقًا (المفتاح المفقود يُرجع نفسه)،
 * فاللغة الأم لا يمكن أن تنكسر بسبب ترجمة ناقصة.
 */
final class TranslationCatalog
{
    public function __construct(private readonly LocaleRegistry $locales) {}

    /**
     * النصوص المستخرَجة من الشيفرة: المصدر الذي يُترجَم منه.
     *
     * @return array<string, array{contexts: array<int, string>, type: string}>
     */
    public function source(): array
    {
        $payload = $this->readJson($this->sourcePath());

        return is_array($payload['entries'] ?? null) ? $payload['entries'] : [];
    }

    /**
     * @param  array<string, array{contexts: array<int, string>, type: string}>  $entries
     */
    public function writeSource(array $entries): void
    {
        ksort($entries);

        $this->writeJson($this->sourcePath(), [
            'version' => 1,
            'count' => count($entries),
            'entries' => $entries,
        ]);
    }

    /**
     * ترجمات لغة واحدة كما ستُقرأ وقت العرض.
     *
     * @return array<string, string>
     */
    public function translations(string $locale): array
    {
        $payload = $this->readJson($this->localePath($locale));

        return array_filter($payload, 'is_string');
    }

    /**
     * @param  array<string, string>  $translations
     */
    public function writeTranslations(string $locale, array $translations): void
    {
        ksort($translations);
        $this->writeJson($this->localePath($locale), $translations);
    }

    /**
     * أصل كل ترجمة: النموذج، وتاريخها، وهل راجعها إنسان.
     *
     * سبب وجوده: الترجمة الآلية غير المراجَعة ليست خطأً، لكنها ليست
     * نهائية. بلا هذا السجل لا يمكن التفريق بين سطر راجعه مترجم وسطر
     * أنتجه نموذج قبل ستة أشهر — فلا يُراجَع أي منهما أبدًا.
     *
     * @return array<string, array{model?: string, at?: string, reviewed?: bool}>
     */
    public function provenance(string $locale): array
    {
        return $this->readJson($this->provenancePath($locale));
    }

    /**
     * @param  array<string, array{model?: string, at?: string, reviewed?: bool}>  $records
     */
    public function writeProvenance(string $locale, array $records): void
    {
        ksort($records);
        $this->writeJson($this->provenancePath($locale), $records);
    }

    /**
     * ما ينقص لغةً بعينها: مفاتيح في المصدر بلا ترجمة.
     *
     * @return array<int, string>
     */
    public function missing(string $locale): array
    {
        $translated = $this->translations($locale);

        return array_values(array_filter(
            array_keys($this->source()),
            fn (string $key): bool => ! isset($translated[$key]) || trim($translated[$key]) === '',
        ));
    }

    /**
     * ترجمات لمفاتيح لم تعد في الشيفرة: تُحذف كي لا يتضخّم الملف بنصوص
     * ماتت مع الشاشة التي كانت تعرضها.
     *
     * @return array<int, string>
     */
    public function orphans(string $locale): array
    {
        $source = $this->source();

        return array_values(array_filter(
            array_keys($this->translations($locale)),
            fn (string $key): bool => ! isset($source[$key]),
        ));
    }

    public function sourcePath(): string
    {
        return base_path((string) config('locales.build.catalog', 'lang/_source/catalog.json'));
    }

    public function localePath(string $locale): string
    {
        return base_path('lang/'.$locale.'.json');
    }

    public function provenancePath(string $locale): string
    {
        return base_path('lang/_source/provenance/'.$locale.'.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        File::ensureDirectoryExists(dirname($path));

        File::put($path, json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        )."\n");
    }
}
