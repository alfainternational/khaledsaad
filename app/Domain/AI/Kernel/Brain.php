<?php

namespace App\Domain\AI\Kernel;

use App\Domain\AI\Kernel\Memory\MemoryScanner;
use Illuminate\Support\Facades\Cache;

/**
 * العقل: المنسّق الذي "يستيقظ" داخل أي طلب عادي، يفكّر مرة واحدة، ثم يموت.
 *
 * دورة التفكير (بلا أي عملية خلفية دائمة):
 *   1) استرجاع ذاكرة ذات صلة (MemoryScanner — رخيص، MySQL فقط).
 *   2) اختيار المهارة المناسبة من السجلّ.
 *   3) تشغيلها (نتيجة محلية قوية أولاً؛ صقل LLM اختياري داخل المهارة).
 *   4) كاش النتيجة لتفادي إعادة الحساب/الإنفاق.
 *
 * النظير في cloud: coordinator/ + query.ts (حلقة الوكيل)، لكن مضغوطة في طلب واحد.
 */
class Brain
{
    public function __construct(
        private readonly SkillRegistry $skills,
        private readonly MemoryScanner $memory,
    ) {}

    public function think(AgentContext $context): SkillResult
    {
        $context = $context->withMemories($this->memory->relevant($context));

        $skill = $this->skills->resolveFor($context);
        if ($skill === null) {
            return SkillResult::empty();
        }

        $cacheKey = $this->cacheKey($context, $skill->code());

        /** @var SkillResult $result */
        $result = Cache::remember($cacheKey, now()->addMinutes(30), fn (): SkillResult => $skill->run($context));

        return $result;
    }

    private function cacheKey(AgentContext $context, string $skillCode): string
    {
        $fingerprint = hash('sha256', implode('|', [
            $skillCode,
            $context->intent,
            (string) ($context->workspace?->getKey() ?? '0'),
            (string) ($context->project?->getKey() ?? '0'),
            json_encode($context->signals, JSON_UNESCAPED_UNICODE),
        ]));

        return 'brain:'.$fingerprint;
    }
}
