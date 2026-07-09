<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * قاضي الجودة (LLM-as-judge عبر Gemini): يقيس جودة *المحتوى* المكتوب أو المولّد
 * مقابل تعليمات الحقل — لا طول النص ولا عدد الكلمات.
 *
 * Cascade-friendly: يُستدعى فقط عند الحاجة (حقول حدّية أو تقييم صريح)، ومُكاش
 * بمفتاح المحتوى. يتدهور بأمان (يعيد null) عند غياب LLM أو Kill Switch أو التعطيل.
 */
class QualityJudge
{
    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly AiMetrics $metrics,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.ai.quality_judge', true)
            && ! (bool) config('services.ai.kill_switch', false)
            && (config('services.gemini.key') || config('services.nvidia.key'));
    }

    /**
     * تقييم جودة استمارة كاملة بنداء واحد (لدمج inline سريع في تقييم الأدوات).
     *
     * @param  array<int, array{label: string, value: string}>  $fields
     * @return array{score: int, note: string}|null
     */
    public function scoreInputs(string $toolName, array $fields): ?array
    {
        $fields = array_values(array_filter($fields, fn ($f): bool => isset($f['value']) && trim((string) $f['value']) !== ''));
        if (! $this->enabled() || $fields === []) {
            return null;
        }

        $block = implode("\n", array_map(
            fn (array $f): string => '- '.(string) $f['label'].': '.trim((string) $f['value']),
            $fields,
        ));

        $cacheKey = 'quality_judge_form:v1:'.hash('sha256', $toolName.'|'.$block);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($toolName, $block): ?array {
            $this->metrics->incr('judge.calls');

            $prompt = implode("\n", [
                'قيّم جودة *مضمون* إجابات المستخدم في أداة «'.$toolName.'» — لا الطول ولا عدد الكلمات.',
                '',
                'الإجابات:',
                $block,
                '',
                'معيار: ارفع الدرجة للإجابات المحدّدة الملموسة وثيقة الصلة؛ اخفضها بشدّة للحشو العام («حلول مبتكرة»، «جودة عالية»، «جميع العملاء») مهما طال.',
                '',
                'أعد JSON فقط: {"score": رقم 0-100 لجودة المضمون الكلية, "note": "ملاحظة موجزة واحدة بالعربية"}',
            ]);

            $system = 'أنت مقيّم خبير صارم. تكافئ التحديد والصلة وتعاقب الحشو. تقيس المضمون لا الطول. أعد JSON صالحاً فقط.';

            $text = $this->gateway->generateText($prompt, $system);
            if (! $text) {
                return null;
            }

            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
            $parsed = json_decode((string) $clean, true);
            if (! is_array($parsed) || ! isset($parsed['score'])) {
                return null;
            }

            return [
                'score' => max(0, min(100, (int) $parsed['score'])),
                'note' => Str::limit(trim((string) ($parsed['note'] ?? '')), 240, '…'),
            ];
        });
    }

    /**
     * تقييم دلالي لكل حقل بنداء واحد: هل تُجيب الإجابة *هذا السؤال تحديداً*؟
     *
     * @param  array<int, array{key: string, question: string, value: string}>  $items
     * @return array<string, array{score: int, note: string}>|null
     */
    public function scoreFields(string $toolName, array $items): ?array
    {
        $items = array_values(array_filter($items, fn ($i): bool => isset($i['key'], $i['value']) && trim((string) $i['value']) !== ''));
        if (! $this->enabled() || $items === []) {
            return null;
        }

        $fp = hash('sha256', $toolName.'|'.implode("\n", array_map(
            fn (array $i): string => $i['key'].'|'.trim((string) $i['question']).'|'.trim((string) $i['value']),
            $items,
        )));
        $cacheKey = 'quality_judge_fields:v4:'.$fp;

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($toolName, $items): ?array {
            // جولة أولى لكل الحقول.
            $raw = $this->classifyFields($toolName, $items);
            if ($raw === null) {
                return null;
            }

            // أولوية «دقّة كل مدخل»: إعادة محاولة واحدة للحقول التي أسقطها النموذج.
            $missing = array_values(array_filter(
                $items,
                fn (array $i): bool => ! isset($raw[$i['key']]) || ! is_array($raw[$i['key']]) || ! isset($raw[$i['key']]['relevance']),
            ));
            if ($missing !== [] && count($missing) < count($items)) {
                $retry = $this->classifyFields($toolName, $missing);
                if (is_array($retry)) {
                    $raw = array_merge($raw, $retry);
                }
            }

            $out = [];
            foreach ($items as $i) {
                $v = $raw[$i['key']] ?? null;
                if (! is_array($v) || ! isset($v['relevance'])) {
                    continue;
                }
                $out[$i['key']] = [
                    'score' => $this->mapClassificationToScore((string) $v['relevance'], (bool) ($v['specific'] ?? false)),
                    'note' => Str::limit(trim((string) ($v['note'] ?? '')), 220, '…'),
                ];
            }

            return $out === [] ? null : $out;
        });
    }

    /**
     * جولة تصنيف واحدة عبر Gemini. يعيد خريطة خام [key => {relevance, specific, note}] أو null.
     *
     * @param  array<int, array{key: string, question: string, value: string}>  $items
     * @return array<string, array<string, mixed>>|null
     */
    private function classifyFields(string $toolName, array $items): ?array
    {
        $this->metrics->incr('judge.calls');

        $block = implode("\n", array_map(
            fn (array $i): string => '['.$i['key'].'] السؤال: '.trim((string) $i['question']).' | الإجابة: '.trim((string) $i['value']),
            $items,
        ));

        // Gemini يُصنّف فقط (مستقر)؛ PHP يحوّل التصنيف لدرجة حتمية (يزيل تذبذب الأرقام).
        $prompt = implode("\n", [
            'لأداة «'.$toolName.'»، صنّف إجابة المستخدم لكل حقل (لا تعطِ أرقاماً). **قيّم كل حقل دون استثناء**.',
            '',
            '"relevance": كيف تجيب الإجابة عن **هذا السؤال تحديداً**؟',
            '  - "answers": تجيب السؤال فعلاً.',
            '  - "partial": تلمس السؤال لكنها ناقصة.',
            '  - "off_topic": لا تجيب السؤال، أو عن موضوع آخر، أو تصف المشروع/الجمهور بدل الإجابة، أو تعيد صياغة السؤال، أو تناسب سؤالاً مختلفاً.',
            '"specific": هل الإجابة محدّدة وملموسة (رقم/زمن/فئة/سلوك)؟ true أو false. (الطول لا يعني التحديد).',
            '',
            'مثال: سؤال «متى تريد أن تصل إليه؟» وإجابة «في مشروعي لجمهور المتاجر» → relevance=off_topic (لا توقيت).',
            'مثال: سؤال «ما أهم هدف؟» وإجابة «الوصول إلى 500 عميل خلال 6 أشهر» → relevance=answers, specific=true.',
            '',
            'الحقول:',
            $block,
            '',
            'في "relevance" ضع قيمة واحدة فقط من: answers أو partial أو off_topic (لا تكتب القائمة كلها).',
            'أعد JSON فقط لكل المفاتيح المذكورة بأقواسها []، بهذا الشكل (استبدل النقاط بالقيم الفعلية):',
            '{"fields":{"<ضع_المفتاح_هنا>":{"relevance":"...","specific":false,"note":"سبب موجز بالعربية"}}}',
        ]);

        $system = 'أنت مقيّم خبير. صنّف كل حقل بدقّة وثبات حسب مطابقة الإجابة للسؤال، ولا تُسقط أي حقل. اختر قيمة relevance واحدة فقط ولا تنسخ قائمة الخيارات. أعد JSON صالحاً فقط بلا أي نص حوله.';

        $text = $this->gateway->generateText($prompt, $system);
        if (! $text) {
            return null;
        }

        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
        $parsed = json_decode((string) $clean, true);

        return is_array($parsed['fields'] ?? null) ? $parsed['fields'] : null;
    }

    /** تحويل تصنيف Gemini إلى درجة حتمية ثابتة. */
    private function mapClassificationToScore(string $relevance, bool $specific): int
    {
        return match ($relevance) {
            'off_topic' => 12,
            'partial' => $specific ? 55 : 38,
            'answers' => $specific ? 90 : 65,
            default => $specific ? 70 : 45,
        };
    }

    /**
     * @return array{score: int, reason: string}|null
     */
    public function score(string $label, string $instructions, string $value): ?array
    {
        $value = trim($value);
        if (! $this->enabled() || $value === '') {
            return null;
        }

        $cacheKey = 'quality_judge:v2:'.hash('sha256', $label.'|'.$instructions.'|'.$value);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($label, $instructions, $value): ?array {
            $this->metrics->incr('judge.calls');

            $prompt = implode("\n", [
                'قيّم جودة إجابة المستخدم على حقل في أداة تسويقية، على **جودة المضمون فقط** لا الطول.',
                '',
                'الحقل: '.$label,
                'المطلوب: '.($instructions !== '' ? $instructions : 'إجابة واضحة قابلة للبناء عليها'),
                'إجابة المستخدم: «'.$value.'»',
                '',
                'معيار التقييم (مهم):',
                '- ارفع الدرجة للإجابة المحدّدة الملموسة وثيقة الصلة بالمطلوب (فئة دقيقة، أرقام، مكان، سلوك، تفاصيل قابلة للتنفيذ).',
                '- اخفض الدرجة بشدّة للحشو التسويقي العام («حلول مبتكرة»، «جودة عالية»، «جميع العملاء»، «الأفضل») مهما كان منمّقاً أو طويلاً.',
                '- الطول لا يرفع الدرجة؛ إجابة قصيرة محدّدة تتفوّق على فقرة عامة.',
                '',
                'أمثلة: «أمهات 25-34 بالرياض يشترين عبر انستغرام» = جودة عالية (~85). «نخدم جميع العملاء بأفضل جودة» = جودة متدنّية (~15).',
                '',
                'أعد JSON فقط: {"score": رقم 0-100, "reason": "سبب موجز بالعربية"}',
            ]);

            $system = 'أنت مقيّم خبير صارم وعادل. تكافئ التحديد والصلة وتعاقب الحشو العام. تقيس المضمون لا الطول. أعد JSON صالحاً فقط.';

            $text = $this->gateway->generateText($prompt, $system);
            if (! $text) {
                return null;
            }

            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
            $parsed = json_decode((string) $clean, true);

            if (! is_array($parsed) || ! isset($parsed['score'])) {
                return null;
            }

            return [
                'score' => max(0, min(100, (int) $parsed['score'])),
                'reason' => Str::limit(trim((string) ($parsed['reason'] ?? '')), 240, '…'),
            ];
        });
    }
}
