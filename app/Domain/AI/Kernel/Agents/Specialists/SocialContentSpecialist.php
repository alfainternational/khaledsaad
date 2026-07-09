<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي السوشيال — التجسيد المحلي لوكيل social-media-manager.
 *
 * يفحص منشوراً محلياً وفق حدود المنصّة ومبادئ التفاعل: الطول، الخطّاف في السطر
 * الأول، الوسوم، ودعوة الفعل. محلي بالكامل، حتمي. (قدرة مبنية hidden حتى تُكشف
 * عبر flag studio.social.)
 */
class SocialContentSpecialist
{
    /** الحد الأقصى العملي للطول لكل منصّة. */
    private const LIMITS = [
        'twitter' => 280,
        'x' => 280,
        'instagram' => 2200,
        'linkedin' => 3000,
        'facebook' => 2000,
        'tiktok' => 2200,
        'general' => 2200,
    ];

    private const CTA = ['احجز', 'سجّل', 'ابدأ', 'حمّل', 'اطلب', 'جرّب', 'اكتشف', 'تابعنا', 'شارك', 'علّق', 'الرابط'];

    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, hint: string}>}
     */
    public function analyze(string $text, string $platform = 'general'): array
    {
        $text = trim($text);
        $platform = strtolower(trim($platform));
        $limit = self::LIMITS[$platform] ?? self::LIMITS['general'];
        $findings = [];
        $score = 100;

        if ($text === '') {
            return ['score' => 0, 'findings' => [
                $this->f('empty', 'لا يوجد منشور', 'high', 'اكتب المنشور أولاً.'),
            ]];
        }

        // الطول ضمن حدّ المنصّة.
        $len = mb_strlen($text);
        if ($len > $limit) {
            $findings[] = $this->f('too_long', 'المنشور يتجاوز حدّ '.$platform, 'high', 'اختصر إلى أقل من '.$limit.' محرفاً ('.$len.' حالياً).');
            $score -= 20;
        }

        // الخطّاف: أول سطر يجذب (سؤال أو رقم أو تشويق).
        $firstLine = mb_substr($text, 0, 50);
        $hasHook = mb_strpos($firstLine, '؟') !== false
            || preg_match('/\d/u', $firstLine) === 1
            || mb_strlen(trim(explode("\n", $text)[0])) <= 60;
        if (! $hasHook) {
            $findings[] = $this->f('weak_hook', 'السطر الأول لا يجذب', 'medium', 'ابدأ بسؤال أو رقم أو جملة قصيرة صادمة.');
            $score -= 12;
        }

        // الوسوم: 1-5 مثالية.
        $hashtags = preg_match_all('/#[\p{Arabic}\p{L}0-9_]+/u', $text);
        if ($hashtags === 0) {
            $findings[] = $this->f('no_hashtags', 'لا وسوم', 'low', 'أضف 1-3 وسوم دقيقة لزيادة الوصول.');
            $score -= 6;
        } elseif ($hashtags > 8) {
            $findings[] = $this->f('too_many_hashtags', 'وسوم كثيرة تبدو سبام', 'low', 'اقتصر على أهم 3-5 وسوم.');
            $score -= 6;
        }

        // دعوة فعل.
        if (! $this->containsAny($text, self::CTA)) {
            $findings[] = $this->f('no_cta', 'لا دعوة فعل', 'medium', 'اطلب فعلاً واضحاً: «تابعنا»، «الرابط في البايو».');
            $score -= 10;
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
