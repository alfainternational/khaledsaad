<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي رحلة العميل — التجسيد المحلي لوكيل journey-orchestrator.
 *
 * يفحص وصف رحلة عميل محلياً: تغطية المراحل (وعي → اهتمام → قرار → احتفاظ)،
 * تحديد نقطة الاحتكاك، ووجود مقياس. محلي بالكامل، حتمي. (قدرة مبنية hidden —
 * flag modules.journeys.)
 */
class CustomerJourneySpecialist
{
    /** كلمات دالّة على كل مرحلة. */
    private const STAGES = [
        'awareness' => ['وعي', 'اكتشاف', 'انتباه', 'يعرف', 'يسمع'],
        'consideration' => ['اهتمام', 'مقارنة', 'تفكير', 'يبحث', 'تقييم'],
        'decision' => ['قرار', 'شراء', 'تحويل', 'اشتراك', 'طلب'],
        'retention' => ['احتفاظ', 'ولاء', 'تكرار', 'إحالة', 'متابعة', 'ما بعد'],
    ];

    private const STAGE_LABELS = [
        'awareness' => 'الوعي',
        'consideration' => 'الاهتمام',
        'decision' => 'القرار',
        'retention' => 'الاحتفاظ',
    ];

    private const FRICTION = ['احتكاك', 'عائق', 'تردد', 'شك', 'تسرب', 'يتوقف', 'يغادر', 'صعوبة'];

    /**
     * @return array{score: int, covered: array<int, string>, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $journey): array
    {
        $journey = trim($journey);
        if ($journey === '') {
            return ['score' => 0, 'covered' => [], 'findings' => [
                ['code' => 'empty', 'label' => 'لا يوجد وصف للرحلة', 'severity' => 'high', 'hint' => 'صف رحلة عميلك خطوة بخطوة.'],
            ]];
        }

        $covered = [];
        foreach (self::STAGES as $stage => $words) {
            if ($this->containsAny($journey, $words)) {
                $covered[] = self::STAGE_LABELS[$stage];
            }
        }

        $findings = [];
        $score = 40 + count($covered) * 12; // تغطية 4 مراحل = 88.

        $missing = array_diff(array_values(self::STAGE_LABELS), $covered);
        if ($missing !== []) {
            $findings[] = $this->f('missing_stages', 'مراحل غير مغطّاة في الرحلة', 'medium', 'أضف: '.implode('، ', $missing).'.');
        }

        if (! $this->containsAny($journey, self::FRICTION)) {
            $findings[] = $this->f('no_friction', 'لم تُحدَّد نقطة احتكاك', 'medium', 'حدّد أكبر نقطة يتردّد أو يتوقّف عندها العميل.');
            $score -= 8;
        }

        if (preg_match('/\d/u', $journey) !== 1) {
            $findings[] = $this->f('no_metric', 'لا مقياس رقمي للرحلة', 'low', 'أضف مقياساً: معدّل تحويل، زمن، أو نسبة تسرّب.');
            $score -= 5;
        }

        return [
            'score' => max(0, min(100, $score)),
            'covered' => $covered,
            'findings' => $findings,
        ];
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{code: string, label: string, severity: string, hint: string}
     */
    private function f(string $code, string $label, string $severity, string $hint): array
    {
        return ['code' => $code, 'label' => $label, 'severity' => $severity, 'hint' => $hint];
    }
}
