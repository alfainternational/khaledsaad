<?php

namespace App\Modules\AiReadiness;

/**
 * قراءة جواب النموذج: من ذُكر، ومن استُشهد به.
 *
 * منفصل عن الجامع عمدًا: التصنيف هو أكثر ما يُعاد النظر فيه — نكتشف أن اسمًا
 * يُكتب بألف ممدودة مرة وبهمزة مرة، أو أن العلامة تُذكر بلاحقة «للتجارة».
 * كونه صنفًا مستقلًّا يعمل على نصّ محفوظ يعني أن تحسينه يُعيد تصنيف تاريخ
 * كامل بلا استدعاء واحد جديد (§١٤).
 *
 * المطابقة نصّية حتمية لا دلالية: النموذج الذي يقرّر «هل ذُكرت العلامة» يجعل
 * القياس نفسه غير قابل لإعادة الإنتاج، فتنهار المقارنة الزمنية.
 */
class BrandMatcher
{
    /**
     * @return array{brand_mentioned: bool, site_cited: bool, brands_mentioned: array<int, string>, citations: array<int, string>}
     */
    public function read(string $text, string $brand, ?string $site = null): array
    {
        $normalized = $this->normalize($text);
        $citations = $this->citationsIn($text);

        return [
            'brand_mentioned' => str_contains($normalized, $this->normalize($brand)),
            'site_cited' => $this->citesSite($citations, $site),
            'brands_mentioned' => $this->brandsIn($text, $brand),
            'citations' => $citations,
        ];
    }

    /**
     * توحيد الرسم العربي قبل المقارنة.
     *
     * «الأحمد» و«الاحمد» اسم واحد يكتبه الناس بطريقتين، والنموذج يخلط بينهما
     * كما يخلط الناس. المطابقة الحرفية كانت ستعدّ نصف الذكر غيابًا.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ى', 'ة', 'ـ'],
            ['ا', 'ا', 'ا', 'ا', 'ي', 'ه', ''],
            $value,
        );

        // التشكيل لا يُكتب في الأسماء التجارية، ووجوده في جواب النموذج صدفة.
        $value = preg_replace('/[\x{064B}-\x{0652}]/u', '', $value) ?? $value;

        return (string) preg_replace('/\s+/u', ' ', $value);
    }

    /**
     * الروابط المذكورة في الجواب.
     *
     * @return array<int, string>
     */
    private function citationsIn(string $text): array
    {
        preg_match_all('#https?://[^\s\)\]<>"،]+#u', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * @param  array<int, string>  $citations
     */
    private function citesSite(array $citations, ?string $site): bool
    {
        if (blank($site)) {
            return false;
        }

        $host = parse_url($site, PHP_URL_HOST) ?: $site;
        $host = preg_replace('/^www\./i', '', (string) $host);

        foreach ($citations as $citation) {
            $citationHost = preg_replace('/^www\./i', '', (string) (parse_url($citation, PHP_URL_HOST) ?: ''));

            if ($citationHost !== '' && strcasecmp($citationHost, (string) $host) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * أسماء العلامات المذكورة في الجواب.
     *
     * تُلتقط من قوائم النموذج المرقّمة والنقطية: هكذا يجيب النموذج فعلًا على
     * سؤال «مين أفضل...» — بقائمة أسماء. الجملة السردية الحرّة لا تُستخرَج
     * منها أسماء لأن استخراجها يحتاج نموذجًا، والنموذج يكسر الحتمية.
     *
     * @return array<int, string>
     */
    private function brandsIn(string $text, string $brand): array
    {
        $found = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if (! preg_match('/^\s*(?:\d+[\.\)\-]|[-*•])\s*(.+)$/u', $line, $matches)) {
                continue;
            }

            // «متجر القهوة — أفضل تحميص» → الاسم قبل الفاصل.
            $name = trim(preg_split('/[:\-–—]/u', $matches[1])[0] ?? '');
            $name = trim($name, " \t\n\r\0\x0B*_.،,");

            if ($name !== '' && mb_strlen($name) <= 80) {
                $found[] = $name;
            }
        }

        /*
         * العلامة المذكورة في نصّ سردي بلا قائمة تُضاف يدويًّا: غيابها من
         * المقام كان سيرفع حصة الصوت لمن ذُكر في قائمة على حساب من ذُكر نصًّا.
         */
        if ($found === [] && str_contains($this->normalize($text), $this->normalize($brand))) {
            $found[] = $brand;
        }

        return array_values(array_unique($found));
    }
}
