<?php

namespace App\Support\AI;

use App\Domain\AI\Models\AITemplate;

class StudioTemplateContractRegistry
{
    /**
     * @return array<string, mixed>
     */
    public function definitionFor(AITemplate|string|null $template): array
    {
        $code = $template instanceof AITemplate
            ? (string) $template->code
            : (string) $template;

        return ($this->definitions()[$code] ?? $this->fallbackDefinition($code)) + ['code' => $code];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return app(StudioTemplateCatalog::class)->definitions();
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackDefinition(string $code): array
    {
        return [
            'family' => 'generic',
            'deliverable_label' => 'ملف تنفيذي',
            'definition_of_done' => [
                'يحتوي أقساماً واضحة ونصوصاً أو قرارات قابلة للتطبيق.',
                'لا يخلط بين أنواع قوالب مختلفة.',
            ],
            'required_fragments' => [],
            'forbidden_fragments' => [],
            'missing_questions' => [
                'ما الهدف الدقيق من هذا الملف؟',
                'ما النتيجة أو الأصل الذي يجب أن يخرج جاهزاً للاستخدام؟',
            ],
            'critical_context' => ['audience', 'primary_goal'],
            'code' => $code,
        ];
    }
}
