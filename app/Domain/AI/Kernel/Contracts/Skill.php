<?php

namespace App\Domain\AI\Kernel\Contracts;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\SkillResult;

/**
 * مهارة = وحدة كفاءة واحدة للعقل (تشخيص، اقتراح، توصية...).
 * تُسجَّل في SkillRegistry وتُحلّ من الـ container (فتحقن ما تحتاجه).
 *
 * القاعدة: كل مهارة تُنتج نتيجة محلية قوية أولاً؛ صقل LLM اختياري داخلها فقط.
 * النظير في cloud: tools/ + skills/.
 */
interface Skill
{
    /** كود فريد للمهارة. */
    public function code(): string;

    /** هل تستطيع هذه المهارة التصرّف في هذا السياق الآن؟ */
    public function handles(AgentContext $context): bool;

    /** نفّذ وأعد نتيجة موحّدة. */
    public function run(AgentContext $context): SkillResult;
}
