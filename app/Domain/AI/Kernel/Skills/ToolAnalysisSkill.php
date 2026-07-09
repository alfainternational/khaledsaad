<?php

namespace App\Domain\AI\Kernel\Skills;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Contracts\Skill;
use App\Domain\AI\Kernel\SkillResult;
use App\Support\Tooling\ToolInputQualityAssessmentService;

/**
 * مهارة تحليل عامة تغطّي كل أدوات المراحل الخمس (26 أداة).
 *
 * تغلّف المحرّك التحليلي المنظّم الموجود (ToolInputQualityAssessmentService):
 * تحليل محلي كامل بلا LLM لأي أداة. تُستدعى بـ intent='tool_analysis' مع
 * signals: tool_code, tool_name, inputs, (mode).
 */
class ToolAnalysisSkill implements Skill
{
    public function __construct(
        private readonly ToolInputQualityAssessmentService $assessment,
    ) {}

    public function code(): string
    {
        return 'tool_analysis';
    }

    public function handles(AgentContext $context): bool
    {
        return $context->intent === 'tool_analysis'
            && is_string($context->signal('tool_code'))
            && is_array($context->signal('inputs'));
    }

    public function run(AgentContext $context): SkillResult
    {
        $toolCode = (string) $context->signal('tool_code');
        $toolName = (string) ($context->signal('tool_name') ?? $toolCode);
        $inputs = (array) $context->signal('inputs');
        $mode = $context->signal('mode');

        $assessment = $this->assessment->assess(
            toolCode: $toolCode,
            toolName: $toolName,
            inputs: $inputs,
            mode: is_string($mode) ? $mode : null,
            workspaceId: $context->workspace?->getKey(),
            projectId: $context->project?->getKey(),
        );

        $bullets = array_values(array_filter(array_merge(
            array_map(fn ($r): string => (string) $r, (array) ($assessment['recommendations'] ?? [])),
        ), fn (string $s): bool => $s !== ''));

        return new SkillResult(
            code: $this->code(),
            headline: (string) ($assessment['verdict'] ?? 'تحليل المدخلات'),
            body: (string) ($assessment['strategic_note'] ?? ''),
            bullets: array_slice($bullets, 0, 5),
            confidence: (int) ($assessment['score'] ?? 0),
            source: SkillResult::SOURCE_LOCAL,
            actions: [],
            meta: [
                'tool_code' => $toolCode,
                'dimensions' => $assessment['dimensions'] ?? [],
                'strengths' => $assessment['strengths'] ?? [],
                'gaps' => $assessment['gaps'] ?? [],
            ],
        );
    }
}
