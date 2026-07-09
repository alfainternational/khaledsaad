<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي النمو — التجسيد المحلي لوكيل growth-engineer.
 *
 * يفحص فكرة نمو محلياً: وجود حلقة قابلة للتكرار، مقياس نجمة شمالية، إشارة اقتصاد
 * وحدة، وإطار تجربة. محلي بالكامل، حتمي. (قدرة مبنية hidden — flag modules.growth.)
 */
class GrowthLoopSpecialist
{
    private const LOOP = ['حلقة', 'إحالة', 'دعوة', 'صديق', 'تكرار', 'عجلة', 'فيروسي', 'مشاركة', 'k-factor', 'يجلب'];

    private const UNIT_ECON = ['تكلفة اكتساب', 'cac', 'ltv', 'عائد', 'هامش', 'قيمة العميل', 'استرداد', 'payback'];

    private const EXPERIMENT = ['اختبار', 'فرضية', 'تجربة', 'ice', 'rice', 'نقيس', 'نجرّب'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $idea): array
    {
        $idea = trim($idea);
        if ($idea === '') {
            return ['score' => 0, 'findings' => [
                ['code' => 'empty', 'label' => 'لا توجد فكرة نمو', 'severity' => 'high', 'hint' => 'صف فكرة النمو أو الحلقة.'],
            ]];
        }

        $findings = [];
        $score = 45;

        if ($this->containsAny($idea, self::LOOP)) {
            $score += 20;
        } else {
            $findings[] = $this->f('no_loop', 'لا حلقة نمو قابلة للتكرار', 'high', 'حوّل الفكرة إلى حلقة: كل عميل يجلب عميلاً (إحالة، مشاركة).');
        }

        if (preg_match('/\d/u', $idea) === 1) {
            $score += 12;
        } else {
            $findings[] = $this->f('no_metric', 'لا مقياس رقمي واضح', 'medium', 'حدّد مقياساً نجمياً واحداً برقم هدف.');
        }

        if ($this->containsAny($idea, self::UNIT_ECON)) {
            $score += 15;
        } else {
            $findings[] = $this->f('no_unit_econ', 'لا إشارة لاقتصاد الوحدة', 'medium', 'اربط النمو بتكلفة الاكتساب مقابل قيمة العميل.');
        }

        if ($this->containsAny($idea, self::EXPERIMENT)) {
            $score += 8;
        } else {
            $findings[] = $this->f('no_experiment', 'لا إطار تجربة', 'low', 'صغ الفكرة كفرضية قابلة للاختبار بمعيار نجاح.');
        }

        return ['score' => max(0, min(100, $score)), 'findings' => $findings];
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
