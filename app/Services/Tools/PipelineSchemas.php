<?php

namespace App\Services\Tools;

/**
 * مخططات المخرجات الثابتة للمراحل المشتركة بين كل الأدوات.
 * مخطط التركيب النهائي خاص بكل أداة ويعيش في tool_versions.output_schema.
 */
class PipelineSchemas
{
    public static function systemPreamble(): string
    {
        return implode("\n", [
            'أنت محلل تسويق عربي يعمل داخل منصة خالد سعد.',
            'التزم بالقواعد التالية دون استثناء:',
            '1. أعد كائن JSON واحدًا فقط، دون أي نص قبله أو بعده ودون سياج شفري.',
            '2. اكتب كل النصوص بالعربية الفصحى الواضحة، بلا مصطلحات غامضة.',
            '3. لا تخترع أرقامًا أو مصادر أو أسماء عملاء. إن غاب الدليل فاذكر ذلك صراحة.',
            '4. كل استنتاج لا يستند إلى بيانات المستخدم يجب أن يُعلَّم is_assumption = true.',
            '5. اربط كل نتيجة بدليل من بيانات التشغيل عبر الحقل evidence عند توفره.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function gaps(): array
    {
        return [
            'type' => 'object',
            'required' => ['missing', 'conflicts'],
            'properties' => [
                'missing' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'required' => ['field', 'why_it_matters'],
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'why_it_matters' => ['type' => 'string'],
                        ],
                    ],
                ],
                'conflicts' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'required' => ['statement', 'explanation'],
                        'properties' => [
                            'statement' => ['type' => 'string'],
                            'explanation' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function section(): array
    {
        return [
            'type' => 'object',
            'required' => ['headline', 'points'],
            'properties' => [
                'headline' => ['type' => 'string', 'minLength' => 10],
                'points' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'required' => ['text', 'is_assumption'],
                        'properties' => [
                            'text' => ['type' => 'string', 'minLength' => 10],
                            'evidence' => ['type' => 'string'],
                            'is_assumption' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function consistency(): array
    {
        return [
            'type' => 'object',
            'required' => ['issues'],
            'properties' => [
                'issues' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'required' => ['section_key', 'problem'],
                        'properties' => [
                            'section_key' => ['type' => 'string'],
                            'problem' => ['type' => 'string'],
                            'suggested_fix' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * مخطط التركيب النهائي الافتراضي: النتائج والتوصيات والخطوة التالية.
     *
     * @return array<string, mixed>
     */
    public static function synthesis(): array
    {
        return [
            'type' => 'object',
            'required' => ['summary', 'findings', 'next_step'],
            'properties' => [
                'summary' => ['type' => 'string', 'minLength' => 40],
                'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'assumptions' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => ['type' => 'string'],
                ],
                'next_step' => [
                    'type' => 'object',
                    'required' => ['title', 'description'],
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 5],
                        'description' => ['type' => 'string', 'minLength' => 20],
                    ],
                ],
                'findings' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        /*
                         * المطلوب هنا هو ما لا يقوم المنتج بدونه فقط.
                         * category وconfidence لهما قيم افتراضية في ReportComposer،
                         * وفرضهما كشرط قبول كان يُسقط تقريرًا كاملًا بسبب حقل تصنيف.
                         */
                        'required' => ['title', 'description', 'severity', 'is_assumption', 'recommendations'],
                        'properties' => [
                            'title' => ['type' => 'string', 'minLength' => 5],
                            'description' => ['type' => 'string', 'minLength' => 20],
                            'category' => ['type' => 'string'],
                            'severity' => ['type' => 'string', 'enum' => ['critical', 'high', 'medium', 'low']],
                            'evidence' => ['type' => 'string'],
                            'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'is_assumption' => ['type' => 'boolean'],
                            'recommendations' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 3,
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['title', 'description', 'impact', 'effort'],
                                    'properties' => [
                                        'title' => ['type' => 'string', 'minLength' => 5],
                                        'description' => ['type' => 'string', 'minLength' => 20],
                                        'impact' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                                        'effort' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                                        'kpi_hint' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
