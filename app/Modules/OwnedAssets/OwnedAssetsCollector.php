<?php

namespace App\Modules\OwnedAssets;

use App\Models\Project;
use App\Modules\Brain\BrainWriter;
use App\Modules\Shared\Evidence\EvidenceLevel;

/**
 * المحور الثامن: الأصول المملوكة مقابل الجماهير المستأجرة.
 *
 * السؤال الذي يقيسه: كم من جمهورك تستطيع الوصول إليه بلا إذن منصة؟ متجر
 * بمئة ألف متابع وصفر بريد لا يملك جمهوره — يملكه من يملك المنصة.
 *
 * **`owned_ratio` لا يُحسب هنا وهذا مقصود.** المقام «إجمالي الجمهور المتاح»
 * يحتاج مصدرًا قابلًا للتحقق لم يُحسم بعد. حسابه من رقم يكتبه صاحب النشاط
 * يجعل المحور `inferred` — وهو المحور الذي يُباع لأنه `measured` (§٥). رقم
 * تقديري هنا لا يضعف المحور فحسب، بل يهدم أساس تسعيره.
 *
 * ما يفعله الجامع اليوم: يكتب ما يمكن رصده فعلًا (وسيلة الجمع المباشرة من
 * الموقع)، ويترك المقام غائبًا فتظهر التغطية ناقصة صراحةً (§٤.٣).
 */
class OwnedAssetsCollector
{
    /** أنماط نماذج الاشتراك في صفحات عربية وإنجليزية. */
    private const CAPTURE_PATTERNS = [
        '/<input[^>]+type\s*=\s*["\']email["\']/i',
        '/name\s*=\s*["\'](email|newsletter|subscribe)["\']/i',
        '/(اشترك|النشرة|قائمتنا البريدية|بريدك الإلكتروني)/u',
    ];

    public function __construct(private readonly BrainWriter $brain) {}

    /**
     * رصد وسيلة الجمع المباشرة من صفحة الموقع.
     *
     * هذه الحقيقة الوحيدة القابلة للرصد اليوم بلا ربط أدوات: هل يملك الموقع
     * بابًا يحوّل زائرًا إلى جهة اتصال؟ غيابه سبب مباشر لبقاء الجمهور مستأجرًا.
     */
    public function collectFromSite(Project $project, ?string $html): bool
    {
        if ($html === null) {
            // تعذّر الفحص ليس نتيجة فحص: لا تُكتب حقيقة.
            return false;
        }

        $found = false;

        foreach (self::CAPTURE_PATTERNS as $pattern) {
            if (preg_match($pattern, $html)) {
                $found = true;
                break;
            }
        }

        $this->brain->record(
            project: $project,
            key: 'first_party_capture',
            value: $found,
            level: EvidenceLevel::Measured,
            sourceModule: 'OwnedAssets',
            sourceReference: 'site_scan',
        );

        return $found;
    }

    /**
     * حالة المحور: لماذا لا يُحسب `owned_ratio` بعد.
     *
     * تُعرض للمستخدم بدل رقم صامت. الفجوة تُعلن ولا تُخفى (§٤.٣).
     *
     * @return array<string, mixed>
     */
    public function status(Project $project): array
    {
        return [
            'ratio_available' => false,
            'reason' => 'لم يُربط مصدر يقيس إجمالي جمهورك المتاح، فلا يمكن حساب نسبة ما تملكه منه.',
            'next_step' => 'اربط قائمتك البريدية أو أداة إدارة العملاء ليصير الرقم مقيسًا لا مقدَّرًا.',
        ];
    }
}
