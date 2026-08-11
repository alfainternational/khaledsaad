<?php

namespace App\Modules\Shared\I18n;

/**
 * حارس النواب: يتحقق أن الترجمة أبقت كل `:v1` و`:name` كما هي.
 *
 * سبب وجوده: النائب في النص ليس كلمة، بل عقد بين القالب والمترجم. لو
 * ترجم النموذج `:v1` إلى «:القيمة» — وهو خطأ يقع فيه كل نموذج تقريبًا —
 * لظهر للمستخدم رمز خام بدل الرقم، ولا يفشل شيء: لا استثناء، ولا سطر
 * في السجل، ولا اختبار أحمر. الصفحة تُعرض «رصيدك :v1 نقطة» وتُقرأ هكذا
 * لأشهر. لذلك التحقق آليّ وإلزاميّ لا مراجعة بصرية.
 */
final class PlaceholderGuard
{
    /**
     * ما يجب أن يعبر الترجمة كما هو: نواب Laravel، وكيانات HTML،
     * ووسوم HTML المضمّنة في النص.
     */
    private const PATTERNS = [
        'placeholder' => '/:(?<!\\\\:)[A-Za-z_][A-Za-z0-9_]*/u',
        'entity' => '/&[a-zA-Z]+;|&#\d+;/u',
        'tag' => '/<\/?[a-zA-Z][^>]*>/u',

        /*
         * فواصل بنيوية لا علامات ترقيم: `|` و`·` تفصل جزأين في سطر واحد
         * (عنوان الصفحة عن اسم العلامة، والعدد عن أساسه). إسقاط النموذج
         * لأحدها يلصق الجزأين — «Methodologyخالد سعد» — ولا يفشل شيء.
         *
         * الشرطة `—` مستثناة عمدًا: هي ترقيم يختلف استعماله بين اللغات،
         * وفرضُها يرفض ترجمات صحيحة.
         */
        'separator' => '/[|·]/u',
    ];

    /**
     * الرموز التي يجب أن تتطابق بين الأصل والترجمة، مرتّبةً ومعدودة.
     *
     * @return array<string, array<int, string>>
     */
    public function tokens(string $text): array
    {
        $found = [];

        foreach (self::PATTERNS as $name => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $tokens = $matches[0];
            sort($tokens);
            $found[$name] = $tokens;
        }

        return $found;
    }

    /**
     * قائمة المخالفات بين الأصل وترجمته. فارغة = مطابقة.
     *
     * @return array<int, string>
     */
    public function violations(string $source, string $translation): array
    {
        $problems = [];
        $sourceTokens = $this->tokens($source);
        $targetTokens = $this->tokens($translation);

        foreach ($sourceTokens as $name => $expected) {
            $actual = $targetTokens[$name];

            $lost = array_diff($expected, $actual);
            $added = array_diff($actual, $expected);

            if ($lost !== []) {
                $problems[] = $name.': مفقود '.implode(' ', array_unique($lost));
            }

            if ($added !== []) {
                $problems[] = $name.': زائد '.implode(' ', array_unique($added));
            }

            if ($lost === [] && $added === [] && count($expected) !== count($actual)) {
                $problems[] = $name.': اختلف العدد ('.count($expected).' ← '.count($actual).')';
            }
        }

        if (trim($translation) === '') {
            $problems[] = 'الترجمة فارغة';
        }

        return $problems;
    }

    public function isClean(string $source, string $translation): bool
    {
        return $this->violations($source, $translation) === [];
    }
}
