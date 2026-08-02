<?php

namespace App\Services\Messaging;

/**
 * عقود الاستوديو مع النموذج.
 *
 * المفتاح محصور بقائمة enum مبنية من الشخصيات المرسلة، والعدد مثبَّت
 * بعددها: بذلك يستحيل على النموذج أن يعيد نصًّا واحدًا للجميع، أو يخترع
 * شخصية لم تُرسل، أو يُسقط واحدة صامتًا.
 */
class MessageSchemas
{
    /**
     * @param  array<int, string>  $personaKeys
     * @return array<string, mixed>
     */
    public static function suggestions(array $personaKeys, int $maxLength): array
    {
        return [
            'type' => 'object',
            'required' => ['messages'],
            'properties' => [
                'messages' => [
                    'type' => 'array',
                    'minItems' => count($personaKeys),
                    'maxItems' => count($personaKeys),
                    'items' => [
                        'type' => 'object',
                        'required' => ['persona_key', 'content', 'teaching_note', 'reusable_formula'],
                        'properties' => [
                            'persona_key' => ['type' => 'string', 'enum' => array_values($personaKeys)],
                            'content' => ['type' => 'string', 'minLength' => 20, 'maxLength' => $maxLength],
                            // الشرح خارج النص حتى لا يُنسخ معه إلى الإعلان.
                            'teaching_note' => ['type' => 'string', 'minLength' => 15, 'maxLength' => 300],
                            'reusable_formula' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 120],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * رسالة لكل عميل متوقع بالاسم.
     *
     * بلا score ولا reaction: رقمٌ بجانب اسم إنسان حقيقي يُقرأ كأنه استُطلع،
     * والمنصة لا تملك رأيه ولا تدّعيه.
     *
     * @param  array<int, string>  $prospectKeys
     * @return array<string, mixed>
     */
    public static function prospectMessages(array $prospectKeys, int $maxLength): array
    {
        return [
            'type' => 'object',
            'required' => ['messages'],
            'properties' => [
                'messages' => [
                    'type' => 'array',
                    'minItems' => count($prospectKeys),
                    'maxItems' => count($prospectKeys),
                    'items' => [
                        'type' => 'object',
                        'required' => ['prospect_key', 'content', 'why'],
                        'properties' => [
                            'prospect_key' => ['type' => 'string', 'enum' => array_values($prospectKeys)],
                            'content' => ['type' => 'string', 'minLength' => 20, 'maxLength' => $maxLength],
                            'why' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 240],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $personaKeys
     * @return array<string, mixed>
     */
    public static function tests(array $personaKeys): array
    {
        return [
            'type' => 'object',
            'required' => ['results', 'summary'],
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'minItems' => count($personaKeys),
                    'maxItems' => count($personaKeys),
                    'items' => [
                        'type' => 'object',
                        'required' => ['persona_key', 'score', 'reaction', 'strength', 'objection', 'revised_content'],
                        'properties' => [
                            'persona_key' => ['type' => 'string', 'enum' => array_values($personaKeys)],
                            'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'reaction' => ['type' => 'string', 'minLength' => 20],
                            'strength' => ['type' => 'string', 'minLength' => 10],
                            'objection' => ['type' => 'string', 'minLength' => 5],
                            // التعديل يخص هذه الشخصية وحدها ولا يُدمج مع غيره.
                            'revised_content' => ['type' => 'string', 'minLength' => 20],
                        ],
                    ],
                ],
                // مقارنة وتجربة تالية فقط — لا رسالة موحّدة.
                'summary' => [
                    'type' => 'object',
                    'required' => ['comparison', 'next_experiment'],
                    'properties' => [
                        'comparison' => ['type' => 'string', 'minLength' => 20],
                        'next_experiment' => ['type' => 'string', 'minLength' => 10],
                    ],
                ],
            ],
        ];
    }
}
