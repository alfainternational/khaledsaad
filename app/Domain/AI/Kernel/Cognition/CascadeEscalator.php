<?php

namespace App\Domain\AI\Kernel\Cognition;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\SkillResult;
use App\Domain\AI\Services\AiMetrics;
use Illuminate\Support\Str;

/**
 * Cascade: المحلي أولاً، والتصعيد للـ LLM فقط عند ثقة منخفضة.
 *
 * مبدأ كفاءة (مثل التنفيذ المتدرّج/speculative): معظم الطلبات تُحَل محلياً
 * بصفر تكلفة؛ يُستدعى الـ LLM فقط حين تكون النتيجة المحلية ضعيفة الثقة. هذا
 * يقلّل نداءات LLM لأدنى حد مع الحفاظ على الجودة. يتدهور بأمان (يعيد المحلي).
 */
class CascadeEscalator
{
    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly AiMetrics $metrics,
    ) {}

    public function maybeEscalate(AgentContext $context, SkillResult $local): SkillResult
    {
        if (! $this->shouldEscalate($local)) {
            return $local;
        }

        $improved = $this->escalate($context, $local);

        if ($improved === null) {
            $this->metrics->incr('cascade.failed');

            return $local;
        }

        $this->metrics->incr('cascade.escalated');

        return $improved;
    }

    private function shouldEscalate(SkillResult $local): bool
    {
        if (! (bool) config('services.ai.cascade', true)) {
            return false;
        }
        if ((bool) config('services.ai.kill_switch', false)) {
            return false;
        }
        if ($local->isEmpty() || $local->source !== SkillResult::SOURCE_LOCAL) {
            return false;
        }

        $threshold = (int) config('services.ai.cascade_threshold', 60);

        return $local->confidence < $threshold;
    }

    private function escalate(AgentContext $context, SkillResult $local): ?SkillResult
    {
        $query = (string) $context->signal('query', '');
        $bullets = implode("\n", array_map(fn ($b): string => '- '.(string) $b, $local->bullets));

        $prompt = implode("\n", [
            'النيّة: '.$context->intent,
            $query !== '' ? 'السياق: '.$query : '',
            '',
            'تحليل محلي أوّلي ضعيف الثقة يحتاج تحسيناً:',
            'العنوان: '.$local->headline,
            $local->body !== '' ? 'النص: '.$local->body : '',
            $bullets !== '' ? "النقاط:\n".$bullets : '',
            '',
            'حسّنه إلى نتيجة أدق وأكثر فائدة عملية. أعد JSON فقط بهذا الشكل:',
            '{"headline":"عنوان تقييمي مختصر","body":"جملتان كحد أقصى","bullets":["نقطة عملية 1","نقطة 2","نقطة 3"]}',
        ]);

        $system = 'أنت مستشار تسويق استراتيجي خبير. حسّن التحليل الأولي بإضافة قيمة حقيقية ودقّة. أعد JSON صالحاً فقط بلا أي نص حوله.';

        $text = $this->gateway->generateText($prompt, $system);
        if (! $text) {
            return null;
        }

        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
        $parsed = json_decode((string) $clean, true);

        if (! is_array($parsed) || empty($parsed['headline'])) {
            return null;
        }

        $bulletsOut = array_values(array_filter(
            array_map(fn ($b): string => trim((string) $b), (array) ($parsed['bullets'] ?? [])),
            fn (string $b): bool => $b !== '',
        ));

        return new SkillResult(
            code: $local->code,
            headline: Str::limit(trim((string) $parsed['headline']), 240, '…'),
            body: Str::limit(trim((string) ($parsed['body'] ?? $local->body)), 600, '…'),
            bullets: $bulletsOut !== [] ? $bulletsOut : $local->bullets,
            confidence: max($local->confidence, 80),
            source: SkillResult::SOURCE_HYBRID,
            actions: $local->actions,
            meta: array_merge($local->meta, ['escalated' => true]),
        );
    }
}
