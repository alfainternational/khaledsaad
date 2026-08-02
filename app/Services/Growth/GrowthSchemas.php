<?php

namespace App\Services\Growth;

/**
 * مخططات مخرجات ميزات محرك النمو — نفس فلسفة PipelineSchemas:
 * المخطط يُرسل للنموذج ويُفرض على المخرج عبر StructuredRunner.
 */
class GrowthSchemas
{
    /**
     * حزمة الظهور للآلات: خلاصة + أسئلة وأجوبة + إشارات مصداقية.
     *
     * @return array<string, mixed>
     */
    public static function geoPack(): array
    {
        return [
            'type' => 'object',
            'required' => ['summary', 'faq', 'credibility_signals'],
            'properties' => [
                'summary' => ['type' => 'string', 'minLength' => 60, 'maxLength' => 600],
                'faq' => [
                    'type' => 'array',
                    'minItems' => 5,
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'required' => ['question', 'answer'],
                        'properties' => [
                            'question' => ['type' => 'string', 'minLength' => 8],
                            'answer' => ['type' => 'string', 'minLength' => 20, 'maxLength' => 700],
                        ],
                    ],
                ],
                'credibility_signals' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 6,
                    'items' => ['type' => 'string', 'minLength' => 10],
                ],
            ],
        ];
    }

    /**
     * لوحة الشخصيات الاصطناعية.
     *
     * الحقول مقسومة قصدًا إلى نصفين: نصفٌ يُدخَل حرفيًّا في لوحة الاستهداف
     * (العمر، الجنس، المدن، الاهتمامات، المنصات، مستوى الإنفاق)، ونصفٌ يكتب
     * الرسالة (الدافع، الاعتراض، النبرة، أسلوب الشراء). حقلٌ لا يخدم أحدهما
     * حشوٌ يُطيل البطاقة ولا يقود قرارًا — فلا يدخل هنا.
     *
     * @return array<string, mixed>
     */
    public static function personaPanel(): array
    {
        return [
            'type' => 'object',
            'required' => ['personas'],
            'properties' => [
                'personas' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 4,
                    'items' => [
                        'type' => 'object',
                        'required' => [
                            'name', 'age_range', 'gender', 'role', 'locations',
                            'interests', 'platforms', 'spending_level',
                            'pains', 'motivation', 'objection', 'buying_style', 'tone', 'quote',
                        ],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            // مدى لا رقمًا: لوحات الإعلان تستهدف مدى عمريًّا.
                            'age_range' => ['type' => 'string'],
                            'gender' => ['type' => 'string', 'enum' => ['ذكر', 'أنثى', 'الجنسان']],
                            'role' => ['type' => 'string'],
                            'locations' => [
                                'type' => 'array', 'minItems' => 1, 'maxItems' => 4,
                                'items' => ['type' => 'string'],
                            ],
                            'interests' => [
                                'type' => 'array', 'minItems' => 2, 'maxItems' => 6,
                                'items' => ['type' => 'string'],
                            ],
                            'platforms' => [
                                'type' => 'array', 'minItems' => 1, 'maxItems' => 4,
                                'items' => ['type' => 'string'],
                            ],
                            'spending_level' => ['type' => 'string', 'enum' => ['منخفض', 'متوسط', 'مرتفع']],
                            'pains' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 4,
                                'items' => ['type' => 'string'],
                            ],
                            // دافع واحد واعتراض واحد: هما ما تُبنى عليه رسالتها.
                            'motivation' => ['type' => 'string', 'minLength' => 10],
                            'objection' => ['type' => 'string', 'minLength' => 10],
                            'buying_style' => ['type' => 'string'],
                            'tone' => ['type' => 'string', 'minLength' => 5],
                            'quote' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * نتيجة اختبار رسالة على اللوحة: لكل شخصية ردّها ورسالتها هي.
     *
     * الشخصية وحدة التجربة لا الرسالة: اعتراض المتردد نقيض اعتراض الحسّاس
     * للسعر، فنصٌّ واحد يرضيهما معًا لا يُقنع أيًّا منهما. لذلك المخرج نصٌّ
     * مستقل لكل شخصية، والخلاصة تصف الفروق ولا تُنتج نسخة موحّدة.
     *
     * @return array<string, mixed>
     */
    public static function personaTest(): array
    {
        return [
            'type' => 'object',
            'required' => ['reactions', 'overall'],
            'properties' => [
                'reactions' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 4,
                    'items' => [
                        'type' => 'object',
                        'required' => ['persona', 'score', 'reaction', 'objection', 'angle', 'tailored_message'],
                        'properties' => [
                            'persona' => ['type' => 'string'],
                            'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'reaction' => ['type' => 'string', 'minLength' => 20],
                            'objection' => ['type' => 'string'],
                            // الزاوية منفصلة عن النص حتى لا تُنسخ معه إلى الإعلان.
                            'angle' => ['type' => 'string', 'minLength' => 10],
                            'tailored_message' => ['type' => 'string', 'minLength' => 20, 'maxLength' => 600],
                        ],
                    ],
                ],
                // بلا improved_version: نسخة واحدة «محسّنة للجميع» هي عين ما
                // يكسر التخصيص — تجمع اعتراضات متناقضة في نص لا يخاطب أحدًا.
                'overall' => [
                    'type' => 'object',
                    'required' => ['verdict', 'biggest_risk'],
                    'properties' => [
                        'verdict' => ['type' => 'string', 'minLength' => 20],
                        'biggest_risk' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }
}
