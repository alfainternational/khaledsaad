<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي الإعلانات المدفوعة — التجسيد المحلي لوكيل media-buyer.
 *
 * يفحص فكرة/نص حملة مدفوعة محلياً: وضوح الهدف الواحد، إشارة الجمهور، دعوة الفعل،
 * وخطّاف العنوان. محلي بالكامل، حتمي. (قدرة مبنية hidden — flag modules.campaigns.)
 */
class PaidCampaignSpecialist
{
    private const OBJECTIVES = ['وعي', 'زيارات', 'تحويل', 'مبيعات', 'ليدات', 'تفاعل', 'تنزيل', 'رسائل'];

    private const AUDIENCE = ['جمهور', 'يستهدف', 'الفئة', 'أعمار', 'اهتمامات', 'مدينة', 'الرياض', 'جدة', 'مشابه', 'إعادة استهداف'];

    private const CTA = ['اشترِ', 'احجز', 'سجّل', 'اطلب', 'حمّل', 'تسوّق', 'ابدأ', 'اطلب الآن'];

    private const FILLER = ['أفضل الأسعار', 'جودة عالية', 'الأفضل', 'حلول مبتكرة', 'خدمة متميزة'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $adText): array
    {
        $adText = trim($adText);
        if ($adText === '') {
            return ['score' => 0, 'findings' => [
                ['code' => 'empty', 'label' => 'لا يوجد نص حملة', 'severity' => 'high', 'hint' => 'اكتب فكرة الحملة أو نصها.'],
            ]];
        }

        $findings = [];
        $score = 60;

        if ($this->containsAny($adText, self::OBJECTIVES)) {
            $score += 12;
        } else {
            $findings[] = $this->f('no_objective', 'هدف الحملة غير واضح', 'high', 'حدّد هدفاً واحداً: وعي أو تحويل أو ليدات — لا عدة أهداف.');
        }

        if ($this->containsAny($adText, self::AUDIENCE)) {
            $score += 12;
        } else {
            $findings[] = $this->f('no_audience', 'الجمهور المستهدف غير محدّد', 'medium', 'صف من يرى الإعلان: الفئة والاهتمام والموقع.');
        }

        if ($this->containsAny($adText, self::CTA)) {
            $score += 10;
        } else {
            $findings[] = $this->f('no_cta', 'لا دعوة فعل', 'high', 'أضف دعوة فعل واحدة واضحة تناسب هدف الحملة.');
        }

        if (preg_match('/\d/u', $adText) !== 1) {
            $findings[] = $this->f('no_numbers', 'الإعلان بلا أرقام تجذب', 'low', 'أضف رقماً: خصم، مدة، أو نتيجة.');
            $score -= 6;
        }

        foreach (self::FILLER as $phrase) {
            if (mb_stripos($adText, $phrase) !== false) {
                $findings[] = $this->f('filler', 'عبارات عامة تضعف الإعلان', 'medium', 'استبدل «'.$phrase.'» بمنفعة محدّدة.');
                $score -= 10;
                break;
            }
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
