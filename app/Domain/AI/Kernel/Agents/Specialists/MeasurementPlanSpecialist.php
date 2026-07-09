<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي القياس المتقدّم — التجسيد المحلي لوكيل marketing-scientist.
 *
 * يفحص خطة قياس محلياً وفق صرامة الاستدلال السببي: وجود مجموعة ضابطة، خط أساس،
 * حجم عينة/دلالة، ومقياس رقمي. غياب الضابطة = خطر عالٍ (لا سببية). محلي، حتمي.
 * (قدرة مبنية hidden — flag analytics.advanced.)
 */
class MeasurementPlanSpecialist
{
    private const CONTROL = ['ضابطة', 'holdout', 'تحكم', 'مجموعة مقارنة', 'geo-lift', 'رفع جغرافي', 'incrementality', 'أثر تزايدي'];

    private const BASELINE = ['خط أساس', 'قبل', 'baseline', 'الوضع الحالي', 'مرجع'];

    private const SIGNIFICANCE = ['عينة', 'دلالة', 'ثقة', '95', 'significance', 'sample', 'p-value', 'فترة ثقة'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $plan): array
    {
        $plan = trim($plan);
        if ($plan === '') {
            return ['score' => 0, 'findings' => [
                ['code' => 'empty', 'label' => 'لا توجد خطة قياس', 'severity' => 'high', 'hint' => 'صف كيف ستقيس الأثر.'],
            ]];
        }

        $findings = [];
        $score = 40;

        if ($this->containsAny($plan, self::CONTROL)) {
            $score += 25;
        } else {
            $findings[] = $this->f('no_control', 'لا مجموعة ضابطة', 'high', 'بلا مجموعة ضابطة/holdout لا يمكن إثبات السببية — أضف واحدة.');
        }

        if ($this->containsAny($plan, self::BASELINE)) {
            $score += 15;
        } else {
            $findings[] = $this->f('no_baseline', 'لا خط أساس', 'medium', 'حدّد الوضع قبل التجربة لتقيس الفرق.');
        }

        if ($this->containsAny($plan, self::SIGNIFICANCE)) {
            $score += 15;
        } else {
            $findings[] = $this->f('no_significance', 'لا حجم عينة أو دلالة', 'medium', 'حدّد حجم عينة كافياً ومستوى دلالة (مثلاً 95%).');
        }

        if (preg_match('/\d/u', $plan) !== 1) {
            $findings[] = $this->f('no_metric', 'لا مقياس رقمي', 'low', 'اربط الخطة بمقياس رقمي هدف.');
            $score -= 5;
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
