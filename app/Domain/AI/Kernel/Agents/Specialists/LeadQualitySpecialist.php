<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي جودة الليدات — التجسيد المحلي لوكيل crm-manager.
 *
 * يفحص معايير تأهيل ليد محلياً وفق BANT مع تشديد على الموافقة (GDPR): تطابق ICP،
 * عناصر التأهيل، وضوح المصدر، وموافقة صريحة. غياب الموافقة = خطر عالٍ. محلي، حتمي.
 * (قدرة مبنية hidden — flag modules.crm.)
 */
class LeadQualitySpecialist
{
    private const CONSENT = ['موافقة', 'opt-in', 'اشترك', 'وافق', 'إذن', 'consent', 'سجّل بنفسه'];

    private const FIT = ['تأهيل', 'معيار', 'icp', 'العميل المثالي', 'تطابق', 'مناسب'];

    private const BANT = ['ميزانية', 'سلطة', 'حاجة', 'توقيت', 'قرار', 'budget', 'authority'];

    private const SOURCE = ['مصدر', 'قناة', 'حملة', 'نموذج', 'إعلان', 'إحالة', 'source', 'utm'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $criteria): array
    {
        $criteria = trim($criteria);
        if ($criteria === '') {
            return ['score' => 0, 'findings' => [
                ['code' => 'empty', 'label' => 'لا توجد معايير تأهيل', 'severity' => 'high', 'hint' => 'صف كيف تؤهّل الليد.'],
            ]];
        }

        $findings = [];
        $score = 50;

        // الموافقة أولاً (امتثال).
        if ($this->containsAny($criteria, self::CONSENT)) {
            $score += 20;
        } else {
            $findings[] = $this->f('no_consent', 'لا إشارة لموافقة صريحة', 'high', 'تأكّد من موافقة الليد (opt-in) قبل التواصل — مطلب امتثال.');
        }

        if ($this->containsAny($criteria, self::FIT)) {
            $score += 12;
        } else {
            $findings[] = $this->f('no_fit', 'لا معيار تطابق مع العميل المثالي', 'medium', 'حدّد معيار تطابق الليد مع ICP.');
        }

        if ($this->containsAny($criteria, self::BANT)) {
            $score += 10;
        } else {
            $findings[] = $this->f('no_bant', 'لا عناصر تأهيل (BANT)', 'medium', 'أضف: ميزانية، سلطة القرار، حاجة، توقيت.');
        }

        if ($this->containsAny($criteria, self::SOURCE)) {
            $score += 8;
        } else {
            $findings[] = $this->f('no_source', 'مصدر الليد غير واضح', 'low', 'سجّل مصدر/قناة كل ليد لإسناد الحملات.');
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
