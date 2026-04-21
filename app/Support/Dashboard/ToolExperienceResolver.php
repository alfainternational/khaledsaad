<?php

namespace App\Support\Dashboard;

use App\Domain\Tool\Models\Tool;

class ToolExperienceResolver
{
    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function resolve(Tool $tool, array $profile): array
    {
        $awareness = $profile['awareness_level'] ?? 'guided';
        $persona = $profile['persona'] ?? 'idea';
        $goal = $profile['primary_goal'] ?? null;

        $modes = [
            'guided' => [
                'label' => 'بسيط',
                'description' => 'أسئلة أقل وشرح أوضح؛ مناسب عندما تريد جملة واضحة أو خطوة قريبة من غير تعقيد.',
                'brief_label' => 'ما الذي تحتاج توضيحه أو تبسيطه؟',
                'brief_placeholder' => 'مثال: ساعدني أشرح الفكرة للعميل بجمل بسيطة.',
            ],
            'structured' => [
                'label' => 'مرتّب',
                'description' => 'يربط بين هدفك الآن وسياق المشروع حتى يخرج مخرج جاهز للاستخدام في العمل.',
                'brief_label' => 'ما المخرج الذي تريد استخدامه في الخطة أو العرض؟',
                'brief_placeholder' => 'مثال: أريد نصاً مرتباً ألصقه في العرض أو الخطة مباشرة.',
            ],
            'expert' => [
                'label' => 'مفصّل',
                'description' => 'أسئلة أكثر وتفاصيل أعمق؛ مناسب عندما تريد تحليلاً أدق أو مقارنة بين خيارات.',
                'brief_label' => 'ما القرار أو التحليل الذي تريد دعمه بالتفصيل؟',
                'brief_placeholder' => 'مثال: أريد ربط الفجوة في السوق بالتموضع والتسعير وخطوات القمع.',
            ],
        ];

        $availableModes = collect($modes)
            ->filter(fn (array $mode, string $key): bool => match ($key) {
                'guided' => (bool) $tool->has_guided_mode,
                'structured' => (bool) $tool->has_structured_mode,
                'expert' => (bool) $tool->has_expert_mode,
            })
            ->all();

        $recommendedMode = array_key_exists($awareness, $availableModes)
            ? $awareness
            : array_key_first($availableModes);

        return [
            'persona_label' => PersonaCatalog::label($persona),
            'awareness_label' => AwarenessCatalog::label($awareness),
            'goal_label' => GoalCatalog::label($goal),
            'recommended_mode' => $recommendedMode,
            'modes' => $availableModes,
            'output_type' => $tool->output_type ?? 'structured_output',
            'estimated_minutes' => $tool->estimated_minutes ?? 15,
            'next_actions' => $tool->next_actions_json ?? [],
            'depends_on' => $tool->depends_on_json ?? [],
            'feeds_into' => $tool->feeds_into_json ?? [],
        ];
    }
}
