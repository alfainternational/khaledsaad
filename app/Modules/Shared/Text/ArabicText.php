<?php

namespace App\Modules\Shared\Text;

/**
 * تطبيع النص العربي قبل أي مطابقة أو عدّ.
 *
 * سبب وجوده: المطابقة الحرفية على العربية تفشل بصمت. «أصحاب» و«اصحاب»
 * و«أَصحاب» ثلاث سلاسل مختلفة عند PHP وكلمة واحدة عند القارئ، و«٣» و«3»
 * رقمان مختلفان عند `preg_match` ورقم واحد عند صاحب النشاط. قياسٌ يعتمد على
 * المطابقة بلا تطبيع يعطي درجات مختلفة لإجابتين متطابقتين معنًى — وهو أسوأ من
 * ألّا يقيس، لأنه يبدو دقيقًا.
 */
final class ArabicText
{
    /** الحركات والتطويل: تُحذف قبل المطابقة لا تُطابق. */
    private const DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{0640}]/u';

    /** الأرقام العربية-الهندية ← اللاتينية، حتى يعدّها `\d` الواحد. */
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** صور الحرف الواحد التي يكتبها الناس بالتبادل. */
    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه',
    ];

    /**
     * العدد مع تمييزه الصحيح.
     *
     * **سبب وجوده:** «10 سؤال إضافي داخل 8 تشخيصًا» جملة تكتبها الآلة ولا
     * يكتبها عربي. والتمييز في العربية يتبع العدد لا العكس: ٣–١٠ يليها جمع،
     * و١١ فما فوق يليها مفرد منصوب. أي صفحة تعرض عددًا محسوبًا تحتاج هذا،
     * فمكانه هنا لا في قالب بعينه.
     *
     * @param  string  $singular  المفرد المنصوب: «سؤالًا»
     * @param  string  $plural  جمع القلة: «أسئلة»
     * @param  string|null  $dual  المثنى: «سؤالان» — يسقط إلى الجمع إن غاب
     */
    public static function counted(int $count, string $singular, string $plural, ?string $dual = null): string
    {
        return match (true) {
            $count === 0 => 'لا '.$plural,
            $count === 1 => $singular,
            $count === 2 => $dual ?? ($count.' '.$plural),
            $count <= 10 => $count.' '.$plural,
            default => $count.' '.$singular,
        };
    }

    public static function normalize(string $value): string
    {
        $value = strtr($value, self::DIGITS);
        $value = (string) preg_replace(self::DIACRITICS, '', $value);
        $value = strtr($value, self::LETTERS);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim(mb_strtolower($value));
    }

    /**
     * عدد الكلمات ذات المعنى.
     *
     * الفواصل والشرطات ليست كلمات، وعدّها يجعل «أ، ب، ج» ثلاث كلمات كـ«شركات
     * صغيرة بالرياض». الحد الأدنى لطول الكلمة حرفان: الحروف المفردة رابطة لا
     * معلومة.
     */
    public static function wordCount(string $value): int
    {
        $words = preg_split('/[\s،,؛;\/\|\-–—•\.]+/u', self::normalize($value)) ?: [];

        return count(array_filter($words, fn (string $word) => mb_strlen(trim($word)) > 1));
    }

    /**
     * عدد المقاطع المفصولة صراحةً — مؤشر على أن الإجابة أكثر من شريحة واحدة.
     */
    public static function segmentCount(string $value): int
    {
        $segments = preg_split('/[،,؛;\/\|\n•]+|\s-\s/u', self::normalize($value)) ?: [];

        return count(array_filter($segments, fn (string $segment) => self::wordCount($segment) >= 2));
    }

    /**
     * @param  array<int, string>  $needles
     */
    public static function containsAny(string $value, array $needles): bool
    {
        $haystack = self::normalize($value);

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, self::normalize($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * تحويل أي قيمة إجابة إلى نصّ واحد قابل للقياس.
     *
     * المتكرر (`repeater`) يصل مصفوفةً، والمدى يصل كائنًا. القياس على النصّ
     * المضموم لا على كل عنصر: المستخدم أجاب إجابة واحدة موزّعة على خانات.
     */
    public static function flatten(mixed $value): string
    {
        if (is_array($value)) {
            return implode('، ', array_map(self::flatten(...), $value));
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
