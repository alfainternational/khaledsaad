<?php

namespace App\Modules\Shared\I18n;

use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Throwable;

/**
 * مترجم النصوص بالنموذج — يُشغَّل عند البناء لا عند الطلب.
 *
 * لماذا مرة واحدة لا كل طلب؟
 *
 * ثلاثة أسباب، كلها حاسمة:
 *
 * ١) **الثبات.** النموذج غير حتميّ. الترجمة وقت الطلب تعني أن الزر
 *    اسمه «Start» اليوم و«Begin» غدًا في نفس الصفحة، فيفقد المستخدم
 *    ثقته بالواجهة قبل أن يفقدها بالمنتج.
 * ٢) **التكلفة.** §٤.٤ يفرض سقف استعلامات لكل مساحة عمل. ترجمة واجهة
 *    عند كل طلب تستهلك السقف على نصٍّ لا يتغيّر.
 * ٣) **المراجعة.** الترجمة المخبوزة في ملف يمكن أن يفتحها مترجم بشري
 *    ويصحّحها فتبقى مصحَّحة. الترجمة اللحظية تُعاد ولادتها كل مرة،
 *    فالتصحيح البشري مستحيل أصلًا.
 *
 * دقة الترجمة تُبنى هنا بثلاث طبقات لا بطبقة واحدة: معجم مقفل يمنع
 * اجتهاد النموذج في مصطلحات المنهجية، وسياق يذكر أين يُعرض النص، وحارس
 * نواب يرفض أي ترجمة كسرت متغيّرًا.
 */
final class AiTranslator
{
    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly LocaleRegistry $locales,
        private readonly PlaceholderGuard $guard,
    ) {}

    /**
     * ترجمة دفعة نصوص إلى لغة واحدة.
     *
     * @param  array<int, string>  $texts
     * @param  array<string, array<int, string>>  $contexts  النص ← الملفات التي يظهر فيها
     * @return array{translations: array<string, string>, failures: array<string, array<int, string>>}
     */
    public function translate(array $texts, string $locale, array $contexts = []): array
    {
        $texts = array_values(array_unique(array_filter($texts, fn ($t) => is_string($t) && trim($t) !== '')));

        if ($texts === []) {
            return ['translations' => [], 'failures' => []];
        }

        $indexed = [];

        foreach ($texts as $position => $text) {
            $indexed[$position + 1] = $text;
        }

        try {
            $payload = $this->runner->run(AIRequest::json(
                messages: [
                    ['role' => 'system', 'content' => $this->systemPrompt($locale)],
                    ['role' => 'user', 'content' => $this->userPrompt($indexed, $contexts, $locale)],
                ],
                schema: $this->schema(count($indexed)),
                tier: (string) config('locales.build.tier', 'standard'),
                stage: 'i18n.translate.'.$locale,
                /*
                 * المترجم يحدّد لغته بنفسه من `$locale` المطلوب. حقن توجيه
                 * لغة الواجهة فيه يجعله يترجم إلى لغة من يشغّل الأمر بدل
                 * اللغة المطلوبة — أي يملأ `fr.json` بالإنجليزية إن كان
                 * الطرفيّ إنجليزيًّا، بلا أي خطأ يظهر.
                 */
                localeNeutral: true,
            ));
        } catch (Throwable $exception) {
            /*
             * كل عطل يُحوَّل إلى فشل مُبلَّغ عنه لا استثناء يصعد.
             *
             * سببه أن الترجمة تُبنى على دفعات متتابعة قد تبلغ المئة: لو
             * أسقط انقطاعُ شبكة في الدفعة الستين العمليةَ كلها، لضاع ثمن
             * تسع وخمسين دفعة دُفع فعلًا. الدفعة الفاشلة تُسجَّل وتُترَك،
             * وإعادة التشغيل تلتقطها وحدها لأن البناء تزايُديّ.
             */
            return [
                'translations' => [],
                'failures' => array_fill_keys($texts, ['تعذّر الحصول على مخرج صالح: '.$exception->getMessage()]),
            ];
        }

        return $this->collect($payload, $indexed);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $indexed
     * @return array{translations: array<string, string>, failures: array<string, array<int, string>>}
     */
    private function collect(array $payload, array $indexed): array
    {
        $translations = [];
        $failures = [];
        $seen = [];

        foreach ((array) ($payload['translations'] ?? []) as $row) {
            $id = (int) ($row['id'] ?? 0);
            $text = trim((string) ($row['text'] ?? ''));

            if (! isset($indexed[$id])) {
                continue;
            }

            $source = $indexed[$id];
            $seen[$id] = true;

            $violations = $this->guard->violations($source, $text);

            if ($violations !== []) {
                $failures[$source] = $violations;

                continue;
            }

            // ترجمة مطابقة للأصل حرفيًا في لغة لاتينية تعني أن النموذج
            // نسخ ولم يترجم. تمريرها يُنتج واجهة إنجليزية بنصوص عربية.
            //
            // إلا أن يكون النصّ كلّه اسمًا في قائمة «لا تُترجَم» — «خالد سعد»
            // مثلًا — فالتطابق حينها هو الصواب لا الفشل، ولولا هذا
            // الاستثناء لصار اسم العلامة نصًّا ناقصًا في كل لغة تُضاف.
            if ($text === $source && ! $this->locales->isProtectedName($source)) {
                $failures[$source] = ['المخرج مطابق للأصل — لم تقع ترجمة'];

                continue;
            }

            $translations[$source] = $text;
        }

        foreach ($indexed as $id => $source) {
            if (! isset($seen[$id]) && ! isset($failures[$source])) {
                $failures[$source] = ['لم يُرجع النموذج ترجمةً لهذا النص'];
            }
        }

        return ['translations' => $translations, 'failures' => $failures];
    }

    private function systemPrompt(string $locale): string
    {
        $language = $this->locales->englishName($locale);
        $native = $this->locales->nativeName($locale);
        $tone = $this->locales->tone($locale);
        $direction = $this->locales->direction($locale);

        $keep = implode(', ', (array) config('locales.glossary.keep', []));

        return <<<PROMPT
        You are a senior localization specialist translating a professional
        Arabic B2B marketing-diagnostics SaaS interface into {$language} ({$native}).

        These are INTERFACE strings, not prose. Each one appears on a screen:
        a button, a label, a heading, a help sentence, a table column, an
        error message. Translate for a user who will read it in that place —
        not for a reader of a document.

        TONE FOR THIS LANGUAGE:
        {$tone}

        HARD RULES — a violation makes the output unusable:

        1. PLACEHOLDERS. Tokens like :v1, :v2, :name, :count are runtime
           values. Reproduce every one EXACTLY, character for character.
           Never translate, renumber, remove, or add one. You may move a
           placeholder to wherever the target grammar requires.
        2. HTML. If the source contains tags or entities (<strong>, &nbsp;),
           keep them identical and in a position that still makes sense.
        3. NEVER TRANSLATE these names: {$keep}
        4. GLOSSARY. Where a term below appears, use the given translation
           exactly. These are product terms under a naming contract; a
           synonym is a defect even when it reads better.
        5. LENGTH. Interface space is fixed. Stay close to the source length.
           A label of two Arabic words must not become a seven-word phrase.
        6. REGISTER. Buttons and menu items are short and imperative.
           Headings are noun phrases. Help text is a full sentence.
        7. NUMERALS. Convert Arabic-Indic digits (٠١٢٣) to the digits normal
           for the target language. Keep the % sign on the side that language
           writes it. Note: the source may write ٪ — output the correct sign.
        8. Do not explain, annotate, transliterate, or add anything the source
           does not say. No trailing periods that the source lacks.
        9. Text direction of the target is {$direction}. Do not insert any
           bidirectional control characters.
        10. CAPITALIZATION. The glossary fixes WORDING, not letter case.
            Inside a running sentence, write a glossary term the way the
            target language writes an ordinary noun there — "the diagnosis
            explains your project's status", not "the Diagnosis explains
            your Project's status". Capitalize only at the start of a label
            or where the language always capitalizes.
        11. FRAGMENTS. Some strings are sentence fragments that continue a
            neighbouring string — they may start mid-sentence, start with
            punctuation, or end without a full stop. Translate them as the
            fragment they are. Never complete them into a sentence, and
            never add or remove leading/trailing punctuation.

        Return ONLY the JSON object required by the schema.
        PROMPT;
    }

    /**
     * @param  array<int, string>  $indexed
     * @param  array<string, array<int, string>>  $contexts
     */
    private function userPrompt(array $indexed, array $contexts, string $locale): string
    {
        $glossary = $this->glossaryFor($locale);
        $lines = [];

        foreach ($indexed as $id => $text) {
            $where = $contexts[$text] ?? [];
            $hint = $where === [] ? '' : ' | shown in: '.implode(', ', array_slice($where, 0, 3));

            $lines[] = $id.'. '.$text.$hint;
        }

        $body = implode("\n", $lines);

        $glossaryBlock = $glossary === []
            ? '(no locked terms apply to this batch)'
            : implode("\n", array_map(
                fn (string $ar, string $target): string => '- '.$ar.' → '.$target,
                array_keys($glossary),
                array_values($glossary),
            ));

        return <<<PROMPT
        LOCKED GLOSSARY (use exactly where the concept appears):
        {$glossaryBlock}

        STRINGS TO TRANSLATE (id. text | context):
        {$body}

        Return one entry per id. Preserve every placeholder.
        PROMPT;
    }

    /**
     * المصطلحات المقفلة للغة الهدف وحدها.
     *
     * @return array<string, string>
     */
    private function glossaryFor(string $locale): array
    {
        $terms = (array) config('locales.glossary.terms', []);
        $resolved = [];

        foreach ($terms as $arabic => $byLocale) {
            if (is_array($byLocale) && isset($byLocale[$locale]) && is_string($byLocale[$locale])) {
                $resolved[(string) $arabic] = $byLocale[$locale];
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(int $count): array
    {
        return [
            'type' => 'object',
            'required' => ['translations'],
            'properties' => [
                'translations' => [
                    'type' => 'array',
                    'minItems' => $count,
                    'maxItems' => $count,
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'text'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'text' => ['type' => 'string', 'minLength' => 1],
                        ],
                    ],
                ],
            ],
        ];
    }
}
