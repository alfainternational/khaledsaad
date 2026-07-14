<?php

namespace App\Support\Tooling;

/**
 * مفتّش الاتساق التسويقي — يكشف التناقضات بين الحقائق القانونية للمشروع وملف
 * المساحة (وبين الحقائق نفسها) لحظياً، فيُنبَّه المستخدم أثناء الإدخال أو بعده
 * بدل أن يتسرّب التناقض إلى مخرج نهائي (كتناقض جمهور الإعلان مع رسالته).
 *
 * حتمي ونقي وقابل للاختبار مباشرةً: يعتمد تشابهاً رمزياً (token overlap) لا
 * نموذجاً خارجياً — فلا يفشل ولا يستهلك رصيداً ولا يُبطئ الإدخال.
 */
class MarketingConsistencyInspector
{
    /** أدنى تداخل رمزي مقبول بين قيمتين لنفس الحقل قبل اعتبارهما متناقضتين. */
    private const OVERLAP_THRESHOLD = 0.16;

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, array{code: string, severity: string, field: string, title: string, message: string, values: array{canonical: string, profile: string}, suggestion: string}>
     */
    public function inspect(ProjectCanonicalFacts $facts, array $profile): array
    {
        $findings = [];

        $idealCustomer = $facts->value('ideal_customer');
        $profileAudience = trim((string) ($profile['audience'] ?? ''));

        if ($idealCustomer !== null && $profileAudience !== '' && $this->diverges($idealCustomer, $profileAudience)) {
            $findings[] = [
                'code' => 'audience_drift',
                'severity' => 'warning',
                'field' => 'audience',
                'title' => 'تعارض في تعريف الجمهور',
                'message' => 'الجمهور في إعدادات مشروعك يختلف عن العميل المثالي الذي أنتجته أدواتك. أي محتوى أو إعلان سيخاطب جمهورين متناقضين.',
                'values' => [
                    'canonical' => $idealCustomer,
                    'profile' => $profileAudience,
                ],
                'suggestion' => 'وحّد الجمهور: اعتمد «'.$idealCustomer.'» من أداة العميل المثالي، أو حدّث العميل المثالي إن تغيّر جمهورك فعلاً.',
            ];
        }

        return $findings;
    }

    /** هل تتباعد قيمتان لنفس الحقل حتى تُعدّا متناقضتين؟ (عام لإعادة الاستخدام والاختبار). */
    public function diverges(string $a, string $b): bool
    {
        return $this->overlap($a, $b) < self::OVERLAP_THRESHOLD;
    }

    private function overlap(string $a, string $b): float
    {
        $ta = $this->tokens($a);
        $tb = $this->tokens($b);

        if ($ta === [] || $tb === []) {
            return 1.0; // لا حكم بلا مادة كافية — نتجنّب إنذاراً كاذباً.
        }

        $intersection = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        return $union > 0 ? $intersection / $union : 1.0;
    }

    /** @return array<int, string> */
    private function tokens(string $text): array
    {
        $text = preg_replace('/\x{0640}/u', '', $text) ?? $text;       // حذف التطويل
        $text = preg_replace('/[\p{P}\p{S}]+/u', ' ', $text) ?? $text; // إزالة الترقيم والرموز
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        $stop = ['في', 'من', 'على', 'الى', 'إلى', 'و', 'أو', 'مع', 'عن', 'الذي', 'التي', 'هذا', 'هذه', 'ما', 'هو', 'هي', 'ثم', 'كل', 'بين', 'أن', 'قد'];
        $words = array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, $stop, true),
        );

        return array_values(array_unique($words));
    }
}
