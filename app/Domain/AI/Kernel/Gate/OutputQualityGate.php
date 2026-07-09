<?php

namespace App\Domain\AI\Kernel\Gate;

use App\Domain\AI\Services\QualityJudge;

/**
 * بوابة جودة المخرَجات — التجسيد المحلي لوكيلَي brand-guardian + quality-assurance.
 *
 * أي مخرَج قابل للنشر (محتوى أداة، توليد استوديو، تقرير) يمرّ عبرها قبل التسليم.
 * تتدهور بأمان: عند غياب المقيّم (Kill Switch / لا مفتاح LLM) لا تحجب المحتوى —
 * تعيد verdict=pass بلا درجة، فتبقى الخدمة المحلية كاملة.
 *
 * القرار متدرّج: pass (≥60) · warn (<60) · empty (بلا نص) · لا حجب صامت أبداً.
 */
class OutputQualityGate
{
    private const PASS_THRESHOLD = 60;

    public function __construct(
        private readonly QualityJudge $judge,
    ) {}

    /**
     * @return array{verdict: string, score: int|null, note: string}
     */
    public function assess(string $subject, string $text, string $instructions = ''): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['verdict' => 'empty', 'score' => null, 'note' => 'لا يوجد محتوى للتقييم.'];
        }

        $judged = $this->judge->score($subject, $instructions, $text);

        // تدهور آمن: تعذّر التقييم الآلي ⇒ لا نحجب، ونصرّح بذلك.
        if ($judged === null) {
            return ['verdict' => 'pass', 'score' => null, 'note' => 'تعذّر التقييم الآلي؛ لم يُحجب المحتوى.'];
        }

        $score = (int) $judged['score'];

        return [
            'verdict' => $score >= self::PASS_THRESHOLD ? 'pass' : 'warn',
            'score' => $score,
            'note' => (string) ($judged['reason'] ?? ''),
        ];
    }

    /** هل المخرَج صالح للنشر؟ (empty وwarn لا يمرّان تلقائياً؛ pass يمرّ). */
    public function passes(string $subject, string $text, string $instructions = ''): bool
    {
        return $this->assess($subject, $text, $instructions)['verdict'] === 'pass';
    }
}
