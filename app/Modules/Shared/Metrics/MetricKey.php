<?php

namespace App\Modules\Shared\Metrics;

/**
 * أسماء المقاييس الرسمية — المرجع الوحيد لما ورد في CLAUDE.md §١٢.
 *
 * السبب: المقياس الذي يُسمّى باسمين يصير مقياسين. قبل هذا الملف كان
 * الموجود base_score وحده، وهو اسم داخلي لا يقابل شيئًا في المواصفة ولا
 * يعني شيئًا للمستخدم. أي اسم خارج هذا الملف يفشل الاختبار المعماري.
 *
 * الأسماء لا تُترجم ولا تُختصر ولا يُشتق منها مرادف في عمود أو استجابة API
 * أو مفتاح Blade.
 */
final class MetricKey
{
    /** المتوسط الموزون لدرجات المحاور المفعّلة (0–100). */
    public const MATURITY_SCORE = 'maturity_score';

    /** درجة محور واحد (0–100). */
    public const AXIS_SCORE = 'axis_score';

    /** نسبة المدخلات المتوفرة لحساب المحور. */
    public const AXIS_COVERAGE = 'axis_coverage';

    /** المحاولات التي ذُكرت فيها العلامة ÷ (الأسئلة × المحاولات لكل سؤال). */
    public const MENTION_RATE = 'mention_rate';

    /** ذكر العلامة ÷ مجموع ذكر كل العلامات. */
    public const SHARE_OF_VOICE = 'share_of_voice';

    /** ظهور السؤال الواحد ÷ محاولاته. */
    public const CONSISTENCY = 'consistency';

    /** مرات ربط الموقع كمصدر ÷ مرات الذكر. */
    public const CITATION_RATE = 'citation_rate';

    /** جهات الاتصال المملوكة ÷ إجمالي الجمهور المتاح. */
    public const OWNED_RATIO = 'owned_ratio';

    /** درجة التدقيق التقني (0–100). */
    public const READINESS_SCORE = 'readiness_score';

    /**
     * كفاية ما وصفه صاحب النشاط عن نفسه (0–100).
     *
     * ليس مرادفًا لـ`axis_coverage` ولا بديلًا عنه: التغطية تقول «هل وصلت
     * المعلومة»، وهذا يقول «هل ما وصل يكفي». إجابة «الجميع» عن الجمهور تعطي
     * تغطية كاملة وكفاية منخفضة — وبلا هذا المقياس كان الاثنان يُقرآن اكتمالًا.
     */
    public const INPUT_FITNESS = 'input_fitness';

    /**
     * فرق متوسط الإشارة بين نافذتي ما قبل الإصلاح وما بعده (§٤.٢).
     *
     * `derived` لا `measured`: حسابٌ فوق إشارات مرصودة. والأخطر أن **نسبته
     * إلى الإصلاح `inferred`** — تزامنٌ زمنيّ لا سببية. عرضه بصيغة الجزم
     * «إصلاحك رفع الزحف» يخالف §٤.١؛ الصيغة الصحيحة «ارتفع الزحف بعد إصلاحك».
     */
    public const SIGNAL_DELTA = 'signal_delta';

    /**
     * مقاييس لا يجوز عرضها في شاشة واحدة بلا تسميتين ظاهرتين.
     *
     * mention_rate وshare_of_voice يُقرآن كنسبتين متشابهتين ومقاماهما
     * مختلفان تمامًا: الأول من محاولاتك أنت، والثاني من ذكر السوق كله.
     * خلطهما يعطي صاحب النشاط قراءة معكوسة عن موقعه.
     *
     * axis_score وinput_fitness كذلك: الأول درجة النشاط، والثاني درجة ما قاله
     * صاحبه عنه. عرضهما بلا تسميتين يجعل صاحب النشاط يقرأ ضعف بياناته ضعفًا في
     * نشاطه، فيصلح الخطأ في المكان الخطأ.
     *
     * @var array<int, array<int, string>>
     */
    public const AMBIGUOUS_PAIRS = [
        [self::MENTION_RATE, self::SHARE_OF_VOICE],
        [self::AXIS_SCORE, self::INPUT_FITNESS],
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::MATURITY_SCORE,
            self::AXIS_SCORE,
            self::AXIS_COVERAGE,
            self::MENTION_RATE,
            self::SHARE_OF_VOICE,
            self::CONSISTENCY,
            self::CITATION_RATE,
            self::OWNED_RATIO,
            self::READINESS_SCORE,
            self::INPUT_FITNESS,
            self::SIGNAL_DELTA,
        ];
    }

    public static function isKnown(string $key): bool
    {
        return in_array($key, self::all(), true);
    }
}
