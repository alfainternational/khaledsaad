<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي العلاقات العامة — التجسيد المحلي لوكيل pr-outreach.
 *
 * يفحص عرض/رسالة PR محلياً: وجود زاوية خبرية، دليل/رقم، طلب واضح، وإيجاز.
 * محلي بالكامل، حتمي. (قدرة مبنية hidden — flag modules.pr.)
 */
class PrOutreachSpecialist
{
    private const ANGLE = ['جديد', 'أول', 'إطلاق', 'حصري', 'دراسة', 'تقرير', 'اتجاه', 'لماذا الآن', 'خبر'];

    private const PROOF = ['رقم', '٪', '%', 'دراسة', 'بيانات', 'نتيجة', 'زيادة', 'نمو', 'مسح'];

    private const ASK = ['تغطية', 'مقابلة', 'نشر', 'حديث', 'تعليق', 'مقال', 'هل تهتم', 'أرفقت'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $pitch): array
    {
        $pitch = trim($pitch);
        if ($pitch === '') {
            return ['score' => 0, 'findings' => [
                ['code' => 'empty', 'label' => 'لا يوجد عرض صحفي', 'severity' => 'high', 'hint' => 'اكتب زاوية الخبر ورسالة العرض.'],
            ]];
        }

        $findings = [];
        $score = 50;

        if ($this->containsAny($pitch, self::ANGLE)) {
            $score += 18;
        } else {
            $findings[] = $this->f('no_angle', 'لا زاوية خبرية', 'high', 'ابدأ بزاوية تستحق النشر: جديد، أول، دراسة، اتجاه.');
        }

        if ($this->containsAny($pitch, self::PROOF)) {
            $score += 15;
        } else {
            $findings[] = $this->f('no_proof', 'لا دليل أو رقم', 'medium', 'أضف رقماً أو دراسة تمنح الخبر مصداقية.');
        }

        if ($this->containsAny($pitch, self::ASK)) {
            $score += 10;
        } else {
            $findings[] = $this->f('no_ask', 'الطلب غير واضح', 'medium', 'اختم بطلب محدّد: تغطية، مقابلة، أو تعليق.');
        }

        // الإيجاز: عرض PR الفعّال قصير.
        $words = count(array_filter(preg_split('/\s+/u', $pitch) ?: []));
        if ($words > 160) {
            $findings[] = $this->f('too_long', 'العرض طويل — الصحفيون يقرأون بسرعة', 'low', 'اختصر إلى فقرتين وأرفق التفاصيل.');
            $score -= 7;
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
