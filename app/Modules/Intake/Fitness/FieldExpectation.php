<?php

namespace App\Modules\Intake\Fitness;

/**
 * ما يجعل الإجابة عن حقل معيّن **كافية**، معلنًا لا مضمرًا.
 *
 * التوقع بيانٌ لا كود، لأنه المرجع الذي يُقاس به وقد يُراجع بشريًّا. ولأنه
 * معلن، صار ممكنًا أن يُعرض للمستخدم قبل أن يجيب: «إجابة كافية هنا تذكر من هم،
 * وأين، ولماذا يشترون» — فالقياس والإرشاد يقرآن التعريف نفسه، ولا يفترقان.
 *
 * فئات العلامات ليست كلمات مفتاحية للحشو: هي أسئلة الصحافي الخمسة مترجمةً إلى
 * ما يُمكن رصده في نصّ عربي بلا نموذج لغوي.
 */
final class FieldExpectation
{
    public const WHO = 'who';

    public const WHERE = 'where';

    public const NEED = 'need';

    public const NUMBER = 'number';

    public const SEGMENTS = 'segments';

    public const CHANNEL = 'channel';

    public const MONEY = 'money';

    /**
     * لغة التمايز: «بينما»، «بدل»، «ما لا يقدّمه غيري».
     *
     * فئة قائمة بذاتها لأن التمايز يُقال بالمقارنة لا بالحاجة. كانت
     * `value_proposition` تطلب `NEED`، فكان وصفٌ ممتاز مثل «نوصّل خلال ساعتين
     * بأسعار الجملة، ما لا يقدّمه المورّد التقليدي» يُحسب ناقصًا لأنه لا يحوي
     * فعل احتياج — وهو أدقّ ما يمكن أن يقوله صاحب نشاط عن تمايزه.
     */
    public const CONTRAST = 'contrast';

    /**
     * @param  array<int, string>  $wants  فئات العلامات المتوقعة في إجابة كافية.
     */
    private function __construct(
        public readonly int $minWords,
        public readonly array $wants,
        public readonly string $sufficientAnswerLooksLike,
    ) {}

    /**
     * توقّع الحقل، أو التوقّع العام لما ليس له تعريف خاص.
     *
     * الافتراض العام موجود عمدًا: القاعدة تسري على **كل** سؤال مفتوح بلا
     * استثناء. حقل بلا تعريف خاص يُقاس بالحد الأدنى المشترك — ولا يُترك بلا
     * قياس، لأن ما لا يُقاس يُحسب كاملًا وهو ليس كذلك.
     */
    public static function for(string $fieldKey): self
    {
        return self::map()[$fieldKey] ?? self::general();
    }

    /**
     * التوقّع العام: طولٌ معقول وحده، بلا فئة علامة مفروضة.
     *
     * **لا يفرض `WHO` و`NEED` عمدًا.** كان يفرضهما، فكان كل حقل نصّي قصير
     * وجيهٍ يُحاسَب بمقياس سؤال الجمهور: «الرياض» جوابًا عن «أين عملاؤك؟» تُقرأ
     * كلمةً واحدة بلا «من» ولا «لماذا» فتنزل إلى أدنى معامل — وتخفض محورًا
     * لإجابة صحيحة تمامًا. الخطأ في اتجاه العقوبة أسوأ من الخطأ في اتجاه
     * التساهل: الأول يدفع صاحب النشاط إلى إصلاح ما ليس مكسورًا.
     *
     * الحقول التي تستحق أكثر تُعلن توقّعها في `map()` صراحةً. ما لا يُعلَن
     * يُقاس بطوله وبلفظه العام وحدهما.
     */
    public static function general(): self
    {
        return new self(
            minWords: 6,
            wants: [],
            sufficientAnswerLooksLike: 'إجابة كافية تذكر تفصيلًا محددًا لا وصفًا عامًّا.',
        );
    }

    /**
     * @return array<string, self>
     */
    private static function map(): array
    {
        return [
            'target_audience' => new self(
                minWords: 10,
                wants: [self::WHO, self::WHERE, self::NEED, self::SEGMENTS],
                sufficientAnswerLooksLike: 'إجابة كافية تسمّي شريحتين أو ثلاثًا، لكل واحدة: من هم (مهنة أو دور أو فئة)، وأين (مدينة أو منطقة)، ولماذا يشترون منك تحديدًا.',
            ),
            'customer_pains' => new self(
                minWords: 8,
                wants: [self::NEED, self::SEGMENTS],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر ثلاثة أوجاع منفصلة بكلام العميل نفسه، لا بكلام التسويق.',
            ),
            'differentiation' => new self(
                minWords: 8,
                wants: [self::CONTRAST],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر ما تفعله ولا يفعله منافسوك، بمقارنة صريحة لا بوصف عام.',
            ),
            'offer_summary' => new self(
                minWords: 8,
                wants: [self::WHO, self::MONEY],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر ما تبيعه، ولمن، وبأي مقابل تقريبي.',
            ),
            'channel_rationale' => new self(
                minWords: 8,
                wants: [self::CHANNEL, self::NEED],
                sufficientAnswerLooksLike: 'إجابة كافية تسمّي القناة وسبب اختيارها المرتبط بمكان وجود عميلك، لا بشعبيتها.',
            ),
            'business_description' => new self(
                minWords: 10,
                wants: [self::WHO, self::WHERE, self::MONEY],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر ما تبيعه، لمن، وفي أي نطاق جغرافي.',
            ),
            'marketing_goal' => new self(
                minWords: 8,
                wants: [self::NUMBER, self::NEED],
                sufficientAnswerLooksLike: 'إجابة كافية تحمل رقمًا ومدةً: «مئة عميل جديد في ستة أشهر» لا «زيادة المبيعات».',
            ),
            'competitors_named' => new self(
                minWords: 4,
                wants: [self::SEGMENTS],
                sufficientAnswerLooksLike: 'إجابة كافية تسمّي ثلاثة منافسين بأسمائهم، لا بأوصافهم.',
            ),

            /*
             * مدخلات محاور قصيرة بطبيعتها.
             *
             * تُعلَن هنا لا لتشديد القياس بل لتخفيفه إلى ما تستحقه: هذه مفاتيح
             * يقرؤها `AxisRegistry` فعلًا، وتركها للتوقّع العام كان يعني أن
             * إجابةً صحيحة من كلمتين تُخفض محورًا بلا سبب.
             */
            'geography' => new self(
                minWords: 1,
                wants: [self::WHERE],
                sufficientAnswerLooksLike: 'إجابة كافية تسمّي مدينة أو دولة أو نطاقًا: «الرياض» أو «الخليج» أو «أونلاين داخل السعودية».',
            ),
            'industry' => new self(
                minWords: 1,
                wants: [],
                sufficientAnswerLooksLike: 'إجابة كافية تضيّق القطاع بكلمتين: «مدارس أهلية» لا «تعليم».',
            ),
            'execution_owner' => new self(
                minWords: 1,
                wants: [],
                sufficientAnswerLooksLike: 'إجابة كافية تسمّي من ينفّذ فعلًا: شخصًا أو دورًا أو جهة.',
            ),
            'description' => new self(
                minWords: 10,
                wants: [self::WHO, self::WHERE],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر ما تبيعه ولمن وفي أي نطاق، بلغة يفهمها من يسمع بنشاطك أول مرة.',
            ),
            'value_proposition' => new self(
                minWords: 8,
                wants: [self::CONTRAST],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر الفرق الذي يلمسه العميل مقارنةً بغيرك: «أوصّل في اليوم نفسه بينما يحتاج غيري ثلاثة أيام» لا «جودة عالية».',
            ),
            'primary_goal' => new self(
                minWords: 3,
                wants: [],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر هدفًا واحدًا محددًا لا ثلاثة أهداف عامة.',
            ),
        ];
    }
}
