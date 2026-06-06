<?php

namespace App\Domain\AI\Kernel\Skills;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Contracts\Skill;
use App\Domain\AI\Kernel\SkillResult;
use App\Support\Dashboard\NextStepRecommendationService;

/**
 * مهارة "الخطوة التالية" — برهان عملي على المعمار:
 * تُنتج توصية قوية بالكامل من المحرك المحلي (NextStepRecommendationService)
 * دون أي نداء LLM ولا أي مورد خارجي. هذا ما يجعلها تعمل "في كل الأوقات".
 */
class NextStepSkill implements Skill
{
    public function __construct(
        private readonly NextStepRecommendationService $nextStep,
    ) {}

    public function code(): string
    {
        return 'next_step';
    }

    public function handles(AgentContext $context): bool
    {
        return $context->workspace !== null;
    }

    public function run(AgentContext $context): SkillResult
    {
        $rec = $this->nextStep->forWorkspace($context->workspace, $context->project);

        $actions = [];
        if (! empty($rec['action_label'])) {
            $actions[] = array_filter([
                'label' => (string) $rec['action_label'],
                'route' => isset($rec['action_route']) ? (string) $rec['action_route'] : null,
            ]);
        }

        return new SkillResult(
            code: $this->code(),
            headline: (string) ($rec['title'] ?? 'خطوتك التالية'),
            body: (string) ($rec['summary'] ?? ''),
            bullets: [],
            confidence: 90,
            source: SkillResult::SOURCE_LOCAL,
            actions: $actions,
            meta: ['stage' => $rec['stage'] ?? null],
        );
    }
}
