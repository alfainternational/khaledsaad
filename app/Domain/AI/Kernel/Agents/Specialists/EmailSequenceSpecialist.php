<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي الإيميل والمتابعة — التجسيد المحلي لوكيل email-specialist.
 *
 * يفحص رسالة/عنوان بريد محلياً وفق مبادئ التسليم والتحويل: قوة العنوان،
 * دعوة الفعل، التخصيص، وإشارات السبام. محلي بالكامل، حتمي. (قدرة مبنية hidden
 * حتى تُكشف عبر entitlement modules.ai_studio + flag studio.email.)
 */
class EmailSequenceSpecialist
{
    /** إشارات سبام تضرّ التسليم. */
    private const SPAM = ['مجاناً 100%', 'اربح', 'عاجل جداً', 'اضغط الآن!!!', '$$$', 'فرصة العمر', 'بدون مجهود'];

    /** كلمات دعوة فعل. */
    private const CTA = ['احجز', 'سجّل', 'ابدأ', 'حمّل', 'اطلب', 'جرّب', 'اكتشف', 'تواصل', 'انضم'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $subject, string $body): array
    {
        $subject = trim($subject);
        $body = trim($body);
        $findings = [];
        $score = 100;

        // العنوان: موجود وبطول عملي (عربي ~ 20-50).
        $subjectLen = mb_strlen($subject);
        if ($subject === '') {
            $findings[] = $this->f('no_subject', 'لا يوجد عنوان للرسالة', 'high', 'العنوان أول ما يقرأه العميل — اكتب عنواناً يثير الفضول.');
            $score -= 25;
        } elseif ($subjectLen > 60) {
            $findings[] = $this->f('subject_long', 'العنوان طويل قد يُبتَر في البريد', 'low', 'اجعل العنوان أقصر من 50 محرفاً ('.$subjectLen.' حالياً).');
            $score -= 8;
        }

        if ($body === '') {
            $findings[] = $this->f('no_body', 'لا يوجد متن للرسالة', 'high', 'اكتب متناً يقدّم قيمة قبل الطلب.');
            $score -= 30;

            return ['score' => max(0, $score), 'findings' => $findings];
        }

        // دعوة فعل واحدة واضحة.
        if (! $this->containsAny($body, self::CTA)) {
            $findings[] = $this->f('no_cta', 'لا دعوة فعل واضحة', 'high', 'أنهِ الرسالة بدعوة واحدة واضحة: «احجز مكانك».');
            $score -= 18;
        }

        // إشارات سبام تضرّ التسليم.
        foreach (self::SPAM as $phrase) {
            if (mb_stripos($subject.' '.$body, $phrase) !== false) {
                $findings[] = $this->f('spam_signal', 'عبارة تثير مرشّحات السبام', 'medium', 'تجنّب «'.$phrase.'» لحماية وصول رسائلك.');
                $score -= 12;
                break;
            }
        }

        // تعدّد دعوات الفعل يشتّت.
        $ctaCount = 0;
        foreach (self::CTA as $c) {
            $ctaCount += mb_substr_count($body, $c);
        }
        if ($ctaCount >= 4) {
            $findings[] = $this->f('too_many_cta', 'دعوات فعل كثيرة تشتّت القارئ', 'low', 'ركّز على دعوة رئيسية واحدة.');
            $score -= 6;
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
