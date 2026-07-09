<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي المؤثّرين — التجسيد المحلي لوكيل influencer-manager.
 *
 * يفحص موجز حملة مؤثّرين محلياً مع تشديد على الإفصاح (FTC): وجود إفصاح إعلاني،
 * وضوح المخرجات، تطابق الجمهور، وآلية قياس. محلي بالكامل، حتمي.
 * (قدرة مبنية hidden — flag modules.influencer.)
 */
class InfluencerSpecialist
{
    private const DISCLOSURE = ['إفصاح', 'إعلان', 'بالتعاون', 'شراكة مدفوعة', 'ممول', 'ad', 'sponsored', 'برعاية'];

    private const DELIVERABLES = ['منشور', 'ريلز', 'ستوري', 'فيديو', 'قصة', 'بوست', 'عدد'];

    private const AUDIENCE = ['جمهور', 'متابعين', 'فئة', 'تطابق', 'اهتمام', 'شريحة'];

    private const MEASURE = ['كود خصم', 'رابط', 'utm', 'قياس', 'تتبع', 'وصول', 'تفاعل', 'مبيعات'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $brief): array
    {
        $brief = trim($brief);
        if ($brief === '') {
            return ['score' => 0, 'findings' => [
                ['code' => 'empty', 'label' => 'لا يوجد موجز حملة', 'severity' => 'high', 'hint' => 'اكتب موجز حملة المؤثّر.'],
            ]];
        }

        $findings = [];
        $score = 55;

        // الإفصاح غير قابل للتفاوض (FTC).
        if ($this->containsAny($brief, self::DISCLOSURE)) {
            $score += 20;
        } else {
            $findings[] = $this->f('no_disclosure', 'لا شرط إفصاح إعلاني', 'high', 'ألزِم المؤثّر بإفصاح واضح («بالتعاون مع» / «إعلان») — مطلب قانوني.');
        }

        if ($this->containsAny($brief, self::AUDIENCE)) {
            $score += 12;
        } else {
            $findings[] = $this->f('no_audience_fit', 'لا معيار تطابق جمهور', 'medium', 'حدّد معيار تطابق جمهور المؤثّر مع عميلك.');
        }

        if ($this->containsAny($brief, self::DELIVERABLES)) {
            $score += 8;
        } else {
            $findings[] = $this->f('no_deliverables', 'المخرجات غير محدّدة', 'medium', 'حدّد المخرجات بدقّة: عدد المنشورات والصيغة.');
        }

        if ($this->containsAny($brief, self::MEASURE)) {
            $score += 5;
        } else {
            $findings[] = $this->f('no_measurement', 'لا آلية قياس', 'low', 'أضف كود خصم أو رابط UTM لقياس الأثر.');
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
