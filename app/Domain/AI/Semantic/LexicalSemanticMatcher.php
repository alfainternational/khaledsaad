<?php

namespace App\Domain\AI\Semantic;

/**
 * تطبيق محلي حتمي للفهم الدلالي — بلا أي نداء خارجي، يعمل «في كل الأوقات».
 *
 * المنهج المتدرّج (من الأقوى للأضعف):
 *   1) تطابق عبارة كاملة (بعد التطبيع) → تعبير صريح (1.0).
 *   2) تطابق مصطلح مفرد بالجذر الخفيف → قوي (0.85).
 *   3) تطابق ضبابي (Levenshtein) لالتقاط صور صرفية لم تُقنَّن → جزئي (0.6).
 * والتشابه العام = تقاطع مجموعتَي الجذور (Jaccard) — ركيزة استرجاع المعرفة.
 *
 * ليس تضمينات عصبية، لكنه ينقل النظام من «مطابقة حرف» إلى «مطابقة مفهوم».
 */
class LexicalSemanticMatcher implements SemanticMatcher
{
    /** عتبة اعتبار المفهوم «معبَّراً عنه». */
    private const EXPRESS_THRESHOLD = 0.5;

    public function __construct(
        private readonly ArabicNormalizer $normalizer,
        private readonly ConceptLexicon $lexicon,
    ) {}

    public function expresses(string $text, string $conceptKey): bool
    {
        return $this->strength($text, $conceptKey) >= self::EXPRESS_THRESHOLD;
    }

    public function strength(string $text, string $conceptKey): float
    {
        $concept = $this->lexicon->concept($conceptKey);
        if ($concept === null || trim($text) === '') {
            return 0.0;
        }

        $normalized = $this->normalizer->normalize($text);
        if ($normalized === '') {
            return 0.0;
        }

        // 1) عبارة كاملة.
        foreach ($concept['phrases'] as $phrase) {
            $np = $this->normalizer->normalize($phrase);
            if ($np !== '' && str_contains($normalized, $np)) {
                return 1.0;
            }
        }

        // جذور توكنات النص (مرة واحدة).
        $textStems = $this->stemSet($this->normalizer->tokens($text));
        if ($textStems === []) {
            return 0.0;
        }

        $best = 0.0;
        foreach ($concept['terms'] as $term) {
            $stem = $this->normalizer->lightStem($this->normalizer->normalize($term));
            if ($stem === '') {
                continue;
            }
            // 2) تطابق جذر مباشر.
            if (isset($textStems[$stem])) {
                $best = max($best, 0.85);

                continue;
            }
            // 3) تطابق ضبابي لالتقاط الصور غير المقنّنة.
            foreach ($textStems as $ts => $_) {
                if ($this->fuzzyEqual($ts, $stem)) {
                    $best = max($best, 0.6);
                    break;
                }
            }
        }

        return $best;
    }

    public function similarity(string $textA, string $textB): float
    {
        $a = $this->stemSet($this->normalizer->tokens($textA));
        $b = $this->stemSet($this->normalizer->tokens($textB));
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($a, $b));
        $union = count($a + $b);

        return $union > 0 ? round($intersection / $union, 4) : 0.0;
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<string, true>  مجموعة جذور (كمفاتيح لبحث O(1))
     */
    private function stemSet(array $tokens): array
    {
        $set = [];
        foreach ($tokens as $token) {
            $stem = $this->normalizer->lightStem($token);
            if ($stem !== '' && mb_strlen($stem) > 1) {
                $set[$stem] = true;
            }
        }

        return $set;
    }

    /** تطابق ضبابي محافظ: مسافة تحرير ≤ 1 لكلمات ≥ 4 أحرف (واعية بـUTF-8). */
    private function fuzzyEqual(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $la = mb_strlen($a);
        $lb = mb_strlen($b);
        if ($la < 4 || $lb < 4 || abs($la - $lb) > 1) {
            return false;
        }

        return $this->mbLevenshtein($a, $b) <= 1;
    }

    /** مسافة تحرير على محارف UTF-8 (PHP levenshtein يعمل على البايتات فيخطئ مع العربية). */
    private function mbLevenshtein(string $a, string $b): int
    {
        $aa = mb_str_split($a);
        $bb = mb_str_split($b);
        $la = count($aa);
        $lb = count($bb);
        if ($la === 0) {
            return $lb;
        }
        if ($lb === 0) {
            return $la;
        }

        $prev = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $aa[$i - 1] === $bb[$j - 1] ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $cur;
        }

        return $prev[$lb];
    }
}
