<?php

namespace App\Domain\AI\Support;

/**
 * محلّل JSON صارم لمخرجات LLM — يضمن جودة الاستخراج مهما تفاوت النموذج.
 *
 * النماذج الأصغر/الأرخص تُخرج JSON «شبه صحيح»: أسوار ```‎، نص قبله/بعده،
 * فواصل زائدة، علامات اقتباس ذكية. هذا المحلّل يقشّر ويستخرج ويُصلح ويتحقّق —
 * فيرفع نسبة النجاح كثيراً ويقلّل التدهور للمحلي بسبب فشل التحليل فقط.
 */
class LlmJsonParser
{
    /**
     * @param  array<int, string>  $requiredKeys  مفاتيح يجب توفّرها لاعتبار الناتج صالحاً
     * @return array<string, mixed>|null
     */
    public static function parse(?string $text, array $requiredKeys = []): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        foreach (self::candidates($text) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded) && self::hasRequired($decoded, $requiredKeys)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * مرشّحات نصّية بالترتيب: الخام → منزوع الأسوار → مقتطف {...} → مُصلَح.
     *
     * @return array<int, string>
     */
    private static function candidates(string $text): array
    {
        $stripped = (string) preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/i', '', trim($text));

        $candidates = [$stripped];

        $start = strpos($stripped, '{');
        $end = strrpos($stripped, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($stripped, $start, $end - $start + 1);
            $candidates[] = $slice;
            $candidates[] = self::repair($slice);
        }

        return array_values(array_unique($candidates));
    }

    private static function repair(string $json): string
    {
        // توحيد علامات الاقتباس الذكية إلى مستقيمة.
        $json = str_replace(
            ['“', '”', '„', '‟', '‘', '’', '‚', '‛'],
            ['"', '"', '"', '"', "'", "'", "'", "'"],
            $json,
        );

        // إزالة الفواصل الزائدة قبل } أو ].
        $json = (string) preg_replace('/,\s*([}\]])/u', '$1', $json);

        return $json;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $requiredKeys
     */
    private static function hasRequired(array $data, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
                return false;
            }
        }

        return true;
    }
}
