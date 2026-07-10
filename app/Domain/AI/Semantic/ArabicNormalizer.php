<?php

namespace App\Domain\AI\Semantic;

/**
 * تطبيع نصّي عربي — يوحّد صور الحرف قبل أي مطابقة دلالية.
 *
 * السبب: المطابقة على الحرف الخام تفشل مع اختلاف التشكيل والهمزات والتاء
 * المربوطة والألف المقصورة والأرقام العربية. التطبيع يرفع الاستدعاء (recall)
 * بشكل حاسم فيتحوّل «يبحث عن كلمة» إلى «يجدها مهما كُتبت». حتمي، بلا أي نداء.
 */
class ArabicNormalizer
{
    /** محارف التشكيل والمدّ التي تُحذف. */
    private const DIACRITICS = [
        "\u{0610}", "\u{0611}", "\u{0612}", "\u{0613}", "\u{0614}", "\u{0615}",
        "\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}", "\u{0650}",
        "\u{0651}", "\u{0652}", "\u{0653}", "\u{0654}", "\u{0655}", "\u{0656}",
        "\u{0657}", "\u{0658}", "\u{0670}", "\u{0640}", // آخرها: تطويل (ـ)
    ];

    /** توحيد صور الحروف. */
    private const LETTER_MAP = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي',
        'ؤ' => 'و',
        'ة' => 'ه',
        'گ' => 'ك', 'ک' => 'ك',
        'ﻻ' => 'لا',
    ];

    /** أرقام عربية/فارسية → لاتينية. */
    private const DIGIT_MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public function normalize(string $text): string
    {
        $text = str_replace(self::DIACRITICS, '', $text);
        $text = strtr($text, self::LETTER_MAP);
        $text = strtr($text, self::DIGIT_MAP);
        $text = mb_strtolower($text, 'UTF-8');

        // توحيد المسافات وعلامات الترقيم إلى فراغ واحد للمطابقة على الكلمات.
        $text = (string) preg_replace('/[\p{P}\p{S}]+/u', ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * كلمات مطبّعة (بلا فراغات)، مع إسقاط الكلمات الشائعة عديمة الدلالة.
     *
     * @return array<int, string>
     */
    public function tokens(string $text): array
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $normalized) ?: [];

        return array_values(array_filter(
            $tokens,
            fn (string $t): bool => $t !== '' && ! in_array($this->stripPrefixes($t), self::STOP_WORDS, true) && mb_strlen($t) > 1,
        ));
    }

    /**
     * جذر خفيف: إزالة سوابق/لواحق شائعة لمطابقة الصرف («الخدمة»↔«خدمات»).
     * ليس جذراً صرفياً كاملاً — تطبيع عملي يرفع التطابق دون تعقيد.
     */
    public function lightStem(string $token): string
    {
        $token = $this->stripPrefixes($token);

        foreach (['اتها', 'اتهم', 'اتنا', 'ونها', 'ات', 'ون', 'ين', 'ان', 'ها', 'هم', 'نا', 'كم', 'ية', 'يه', 'ه', 'ي'] as $suffix) {
            if (mb_strlen($token) - mb_strlen($suffix) >= 3 && str_ends_with($token, $suffix)) {
                return mb_substr($token, 0, mb_strlen($token) - mb_strlen($suffix));
            }
        }

        return $token;
    }

    private function stripPrefixes(string $token): string
    {
        foreach (['وال', 'بال', 'كال', 'فال', 'ال', 'و', 'ف', 'ب', 'ك', 'ل'] as $prefix) {
            if (mb_strlen($token) - mb_strlen($prefix) >= 3 && str_starts_with($token, $prefix)) {
                return mb_substr($token, mb_strlen($prefix));
            }
        }

        return $token;
    }

    /** كلمات وظيفية شائعة تُسقَط من التوكنة (بعد إزالة السوابق). */
    private const STOP_WORDS = [
        'من', 'الى', 'عن', 'على', 'في', 'مع', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي',
        'ان', 'انا', 'انت', 'هو', 'هي', 'هم', 'نحن', 'كل', 'بعض', 'او', 'ثم', 'قد',
        'لكن', 'حتى', 'اذا', 'كان', 'يكون', 'كما', 'بين', 'عند', 'لا', 'ما', 'يا',
    ];
}
