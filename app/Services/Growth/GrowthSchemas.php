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
                        'required' => ['name', 'age_range', 'role', 'pains', 'buying_style', 'quote'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'age_range' => ['type' => 'string'],
                            'role' => ['type' => 'string'],
                            'pains' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 4,
                                'items' => ['type' => 'string'],
                            ],
                            'buying_style' => ['type' => 'string'],
                            'quote' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * نتيجة اختبار رسالة على اللوحة: رد كل شخصية + خلاصة عامة.
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
                        'required' => ['persona', 'score', 'reaction', 'objection'],
                        'properties' => [
                            'persona' => ['type' => 'string'],
                            'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'reaction' => ['type' => 'string', 'minLength' => 20],
                            'objection' => ['type' => 'string'],
                        ],
                    ],
                ],
                'overall' => [
                    'type' => 'object',
                    'required' => ['verdict', 'biggest_risk', 'improved_version'],
                    'properties' => [
                        'verdict' => ['type' => 'string', 'minLength' => 20],
                        'biggest_risk' => ['type' => 'string'],
                        'improved_version' => ['type' => 'string', 'minLength' => 20],
                    ],
                ],
            ],
        ];
    }
}
