<?php

namespace App\Modules\Learning;

use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;

class MarketingLessonAssistant
{
    public function __construct(private readonly StructuredRunner $runner) {}

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function suggest(array $context): array
    {
        $payload = $this->runner->run(AIRequest::json(
            messages: [
                ['role' => 'system', 'content' => implode("\n", [
                    'أنت مساعد تعلم تسويق عربي داخل درس محدد.',
                    'اقرأ سياق الصفحة كاملًا ثم أجب عن الحقل الحالي وحده.',
                    'امنع النصائح العامة أو الكلام الذي يصلح لأي سؤال آخر.',
                    'اربط المساعدة صراحة بمفهوم من الدرس وبمعيار تقييم السؤال.',
                    'إن كانت معلومات الدارس ناقصة، صغ المثال كفرضية واطلب تعديلها، ولا تعرضها كحقيقة.',
                    'أعد JSON مطابقًا للمخطط فقط.',
                ])],
                ['role' => 'user', 'content' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)],
            ],
            schema: $this->schema(),
            tier: 'standard',
            stage: 'marketing_lesson_assist',
        ));

        return $payload + ['evidence_label' => 'فرضية'];
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['field_help', 'example', 'why_it_fits', 'next_action', 'basis'],
            'additionalProperties' => false,
            'properties' => [
                'field_help' => ['type' => 'string', 'minLength' => 20],
                'example' => ['type' => 'string', 'minLength' => 10],
                'why_it_fits' => ['type' => 'string', 'minLength' => 10],
                'next_action' => ['type' => 'string', 'minLength' => 8],
                'basis' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
            ],
        ];
    }
}
