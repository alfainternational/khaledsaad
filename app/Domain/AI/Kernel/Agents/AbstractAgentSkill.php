<?php

namespace App\Domain\AI\Kernel\Agents;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Contracts\Skill;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Kernel\SkillResult;

/**
 * الأساس الموحّد لكل قدرة وكيل داخل النواة. يفرض القاعدة الذهبية للمعمار:
 *
 *   1) نتيجة محلية (rule-based) قوية أولاً        — computeLocal()
 *   2) صقل LLM آخر ميل اختياري لا يكسر شيئاً        — maybeRefine()
 *   3) تسجيل التعلّم في عمود المعرفة (curator)      — learn()
 *
 * التدهور آمن دائماً: غياب أي مورد خارجي يُبقي النتيجة المحلية كاملة. المهارات
 * الوارثة تحدّد code() وcomputeLocal() فقط، وتُضيف maybeRefine() عند الحاجة.
 */
abstract class AbstractAgentSkill implements Skill
{
    /** كود القدرة كما في config/agent_registry.php. */
    abstract public function code(): string;

    /** النتيجة المحلية القوية — بلا أي نداء خارجي. */
    abstract protected function computeLocal(AgentContext $context): SkillResult;

    public function handles(AgentContext $context): bool
    {
        return $context->workspace !== null;
    }

    public function run(AgentContext $context): SkillResult
    {
        $result = $this->computeLocal($context);
        $result = $this->maybeRefine($context, $result);
        $this->learn($context, $result);

        return $result;
    }

    /**
     * آخر ميل: تُعيد النتيجة المحلية كما هي افتراضياً (محلي أولاً). تتجاوزها
     * المهارة التي تريد صقلاً لغوياً عبر LLM — ويجب أن تتدهور لـ $local عند الغياب.
     */
    protected function maybeRefine(AgentContext $context, SkillResult $local): SkillResult
    {
        return $local;
    }

    /** نداء LLM آمن: يعيد null عند أي غياب/فشل ولا يُسقط النتيجة المحلية أبداً. */
    protected function llm(string $prompt, ?string $system = null): ?string
    {
        try {
            return app(AiGatewayInterface::class)->generateText($prompt, $system);
        } catch (\Throwable) {
            return null;
        }
    }

    /** تعريف هذه القدرة من الكتالوج (للحالة/الصلاحية/السطح). */
    protected function definition(): ?AgentDefinition
    {
        return app(AgentCatalog::class)->get($this->code());
    }

    /**
     * عمود التعلّم: سجلّ مضغوط لكل تشغيل ذي نتيجة، يقرأه الاستدلال لاحقاً.
     * أفضل جهد دائماً — لا يكسر الطلب مهما حدث.
     */
    protected function learn(AgentContext $context, SkillResult $result): void
    {
        if ($result->isEmpty() || $context->workspace === null) {
            return;
        }

        try {
            app(KnowledgeStore::class)->remember(
                'agent.'.$this->code().'.ws'.$context->workspace->getKey().'.'.$context->intent,
                [
                    'agent' => $this->code(),
                    'workspace_id' => $context->workspace->getKey(),
                    'project_id' => $context->project?->getKey(),
                    'intent' => $context->intent,
                    'headline' => $result->headline,
                    'confidence' => $result->confidence,
                    'source' => $result->source,
                ],
            );
        } catch (\Throwable) {
            // التعلّم أفضل جهد؛ لا يعطّل الاستجابة.
        }
    }
}
