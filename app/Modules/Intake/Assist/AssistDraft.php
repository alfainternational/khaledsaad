<?php

namespace App\Modules\Intake\Assist;

/**
 * مخرج المساعدة على سؤال واحد: دليل، ومقترحات، وترشيح أفضل خيار.
 *
 * كله `inferred` بلا استثناء (§٤.١). المقترح كلامُ نموذج لغوي عن نشاط لم يره،
 * مبنيٌّ على ما قاله صاحبه — وهو أضعف من فرضية منهجية لا أقوى. عرضه بصيغة الجزم
 * هو الخطر بعينه: لا أن يكون فرضية، بل أن يُقرأ حقيقة (§٤.١).
 */
final class AssistDraft
{
    /**
     * @param  array<int, array{label: string, value: string, why: string}>  $suggestions
     *                                                                                     مقترحات ملموسة: `value` ما يُدخَل في الخانة، و`label` ما يُقرأ في
     *                                                                                     الزر، و`why` سبب ملاءمته لهذا النشاط تحديدًا.
     * @param  array<int, string>  $basis  على أي معلومة بُني هذا. مقترح بلا أساس معلن دعوى.
     */
    public function __construct(
        public readonly string $guide,
        public readonly array $suggestions,
        public readonly ?string $recommendedValue = null,
        public readonly ?string $recommendationReason = null,
        public readonly array $basis = [],
    ) {}

    /**
     * مخرج فارغ صالح — للحالة التي لا يتوفر فيها مزوّد أو ميزانية.
     *
     * الفراغ يُعلن ولا يُلفَّق (§٤.٣): سؤال بلا مساعدة يظهر بلا زر مساعدة، لا
     * بمقترح عام لا يخصّ أحدًا.
     */
    public static function none(): self
    {
        return new self(guide: '', suggestions: []);
    }

    public function isEmpty(): bool
    {
        return trim($this->guide) === '' && $this->suggestions === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guide' => $this->guide,
            'suggestions' => $this->suggestions,
            'recommended_value' => $this->recommendedValue,
            'recommendation_reason' => $this->recommendationReason,
            'basis' => $this->basis,
            'evidence_level' => 'inferred',
            // الوسم النصّي يسافر مع البيانات لا يُترك للواجهة (§١٣).
            'assumption_label' => 'فرضية — مقترح مبني على ما وصفته، راجعه وعدّله قبل اعتماده.',
        ];
    }

    /**
     * المخطط الذي يُفرض على مخرج النموذج.
     *
     * يُرسل المخطط نفسه إلى النموذج لا مجرد «أعد JSON»: صيغة صالحة لا تعني شكلًا
     * صحيحًا، والحقل الناقص يُكتشف عند العرض لا عند التوليد.
     *
     * @return array<string, mixed>
     */
    public static function schema(bool $isChoice): array
    {
        $schema = [
            'type' => 'object',
            'required' => ['guide', 'suggestions'],
            'properties' => [
                'guide' => ['type' => 'string', 'minLength' => 40, 'maxLength' => 700],
                'suggestions' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 4,
                    'items' => [
                        'type' => 'object',
                        'required' => ['label', 'value', 'why'],
                        'properties' => [
                            'label' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 120],
                            'value' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 1200],
                            'why' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 300],
                        ],
                    ],
                ],
                'basis' => [
                    'type' => 'array',
                    'maxItems' => 6,
                    'items' => ['type' => 'string', 'maxLength' => 160],
                ],
            ],
        ];

        if ($isChoice) {
            $schema['required'][] = 'recommended_value';
            $schema['required'][] = 'recommendation_reason';
            $schema['properties']['recommended_value'] = ['type' => 'string', 'maxLength' => 200];
            $schema['properties']['recommendation_reason'] = ['type' => 'string', 'minLength' => 10, 'maxLength' => 300];
        }

        return $schema;
    }
}
