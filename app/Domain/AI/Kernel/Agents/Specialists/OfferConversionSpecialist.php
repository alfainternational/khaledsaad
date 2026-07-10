<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

use App\Domain\AI\Semantic\SemanticMatcher;

/**
 * أخصائي العرض والتحويل — التجسيد المحلي لوكيل cro-specialist.
 *
 * يقيس قوة عرض/رسالة تسويقية محلياً وفق مبادئ CRO الثابتة (تحديد، عكس مخاطرة،
 * دعوة فعل، وضوح الجمهور، منفعة لا خاصية)، ويعيد درجة + نقاط ضعف + اقتراحات
 * قابلة للتنفيذ. لا نداء خارجي — heuristics حتمية تعمل «في كل الأوقات».
 *
 * الفهم دلالي (SemanticMatcher) لا معجمي: يتعرّف على «تدفع لو رضيت» كعكس مخاطرة
 * رغم غياب كلمة «ضمان». السطح: يُحقن في أدوات المرحلة 3 (العرض/التسعير).
 */
class OfferConversionSpecialist
{
    public function __construct(
        private readonly SemanticMatcher $matcher,
    ) {}

    /**
     * تحليل قوة العرض محلياً.
     *
     * @return array{score: int, strengths: array<int, string>, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $offer): array
    {
        $offer = trim($offer);
        if ($offer === '') {
            return ['score' => 0, 'strengths' => [], 'findings' => [
                ['code' => 'empty', 'label' => 'لا يوجد عرض للتقييم', 'severity' => 'high', 'hint' => 'اكتب عرضك أولاً.'],
            ]];
        }

        $findings = [];
        $strengths = [];
        $score = 50; // خط أساس محايد.

        // 1) التحديد: أرقام/نسب/زمن/سعر.
        $hasNumbers = preg_match('/\d/u', $offer) === 1;
        if ($hasNumbers) {
            $score += 15;
            $strengths[] = 'يتضمّن أرقاماً ملموسة (سعر/مدة/نتيجة).';
        } else {
            $findings[] = ['code' => 'no_numbers', 'label' => 'العرض بلا أرقام ملموسة', 'severity' => 'medium', 'hint' => 'أضف رقماً: سعراً، مدة، أو نتيجة متوقّعة.'];
        }

        // 2) عكس المخاطرة / الضمان (دلالياً: يلتقط «تدفع لو رضيت» بلا كلمة «ضمان»).
        if ($this->matcher->expresses($offer, 'risk_reversal')) {
            $score += 15;
            $strengths[] = 'يقلّل مخاطرة العميل (ضمان/تجربة).';
        } else {
            $findings[] = ['code' => 'no_risk_reversal', 'label' => 'لا يوجد عكس مخاطرة', 'severity' => 'medium', 'hint' => 'أضف ضماناً أو تجربة أو استرجاعاً لتقليل تردّد العميل.'];
        }

        // 3) دعوة فعل واضحة.
        if ($this->matcher->expresses($offer, 'cta')) {
            $score += 12;
            $strengths[] = 'يحوي دعوة فعل واضحة.';
        } else {
            $findings[] = ['code' => 'no_cta', 'label' => 'دعوة الفعل غير واضحة', 'severity' => 'high', 'hint' => 'أنهِ العرض بفعل واحد واضح: «ابدأ الآن»، «احجز مكانك».'];
        }

        // 4) الحشو العام يضعف الرسالة (قوّة التعبير تحدّد شدّة العقوبة).
        $fillerStrength = $this->matcher->strength($offer, 'filler');
        if ($fillerStrength > 0.0) {
            $score -= (int) round(min(24, $fillerStrength * 24));
            $findings[] = ['code' => 'filler', 'label' => 'عبارات عامة تُضعف العرض', 'severity' => 'medium', 'hint' => 'استبدل العبارات العامة بمنفعة محدّدة وملموسة (رقم، فئة، نتيجة).'];
        }

        // 5) الطول العملي: قصير جداً أو مترهّل.
        $words = count(array_filter(preg_split('/\s+/u', $offer) ?: []));
        if ($words < 6) {
            $findings[] = ['code' => 'too_short', 'label' => 'العرض قصير جداً', 'severity' => 'low', 'hint' => 'وضّح لمن العرض وما المنفعة الأساسية.'];
            $score -= 8;
        }

        return [
            'score' => max(0, min(100, $score)),
            'strengths' => $strengths,
            'findings' => $findings,
        ];
    }
}
