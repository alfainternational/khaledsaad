<?php

namespace App\Domain\AI\Kernel\Skills;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Cognition\PredictionEngine;
use App\Domain\AI\Kernel\Cognition\ReasoningEngine;
use App\Domain\AI\Kernel\Contracts\Skill;
use App\Domain\AI\Kernel\SkillResult;

/**
 * مهارة الرؤية المعرفية — تجمع القدرات الأربع في نتيجة واحدة:
 * تحليل التغطية + استدلال/استنباط (ReasoningEngine) + تنبؤ (PredictionEngine).
 *
 * تُستدعى بـ intent='insight'. نتيجة محلية بالكامل، تتحسّن مع التعلّم المتراكم.
 */
class InsightSkill implements Skill
{
    public function __construct(
        private readonly ReasoningEngine $reasoning,
        private readonly PredictionEngine $prediction,
    ) {}

    public function code(): string
    {
        return 'insight';
    }

    public function handles(AgentContext $context): bool
    {
        return $context->intent === 'insight' && $context->workspace !== null;
    }

    public function run(AgentContext $context): SkillResult
    {
        $reasoning = $this->reasoning->reason($context->workspace, $context->project);
        $prediction = $this->prediction->predict($context->workspace, $context->project, $reasoning);

        $bullets = [];
        foreach ((array) ($reasoning['deductions'] ?? []) as $d) {
            $bullets[] = (string) $d;
        }

        $blocker = (string) ($prediction['predicted_blocker'] ?? '');
        if ($blocker !== '') {
            $bullets[] = 'العائق المتوقّع: '.$blocker;
        }

        // تجنّب تكرار الخطر الذي عُرض أصلاً كعائق متوقّع.
        foreach (array_slice((array) ($reasoning['risks'] ?? []), 0, 3) as $r) {
            if ((string) $r === $blocker) {
                continue;
            }
            $bullets[] = 'تنبيه منطقي: '.$r;
        }

        $headline = sprintf(
            'إنجاز %d%% · الزخم: %s',
            (int) ($prediction['projected_completion_pct'] ?? 0),
            (string) ($prediction['momentum'] ?? 'غير محدد'),
        );

        return new SkillResult(
            code: $this->code(),
            headline: $headline,
            body: (string) ($prediction['forecast'] ?? ''),
            bullets: array_slice(array_values(array_filter($bullets, fn (string $s): bool => trim($s) !== '')), 0, 6),
            confidence: 85,
            source: SkillResult::SOURCE_LOCAL,
            actions: [],
            meta: [
                'reasoning' => $reasoning,
                'prediction' => $prediction,
            ],
        );
    }
}
