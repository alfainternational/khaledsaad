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

    public static function general(): self
    {
        return new self(
            minWords: 6,
            wants: [self::WHO, self::NEED],
            sufficientAnswerLooksLike: 'إجابة كافية تذكر تفصيلًا محددًا لا وصفًا عامًّا: من، وماذا، ولماذا.',
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
                wants: [self::NEED, self::WHO],
                sufficientAnswerLooksLike: 'إجابة كافية تذكر ما تفعله ولا يفعله منافسوك، ولمن يهمّه هذا الفرق.',
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
        ];
    }
}
