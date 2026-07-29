<?php

namespace App\Modules\PlatformBridge;

use App\Models\Project;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\FixList;
use App\Modules\Diagnosis\MaturityAggregator;

/**
 * نقطة العبور الوحيدة من التشخيص إلى القدرات القديمة.
 *
 * ليست جسرًا شبكيًّا: لا يوجد نظام ثانٍ. تحرس **قاعدة منتج** — أن الخطة
 * المولّدة نتيجة تابعة للتشخيص لا مقترح قيمة (§٢ و§١٥). حدٌّ واحد يُنفّذ ذلك
 * بنيويًّا أفضل من انضباط موزَّع على عشرات الشاشات.
 *
 * ما لا يمر من هنا: الفوترة والصلاحيات والملكية. لها واجهتها بالفعل، ولفّها
 * يضيف طبقة بلا حدّ خارجي تحرسه — ويحرسه اختبار معماري.
 */
class LegacyCapabilities
{
    public function __construct(
        private readonly MaturityAggregator $maturity,
        private readonly FixList $fixes,
    ) {}

    /**
     * لقطة التشخيص كما تُمرَّر إلى مولّد الخطط القائم.
     *
     * هذا ما يجعل الخطة مبنية على قياس النشاط لا على وصف كتبه صاحبه — وهي
     * قيمتها الوحيدة، لأن التوليد نفسه متاح مجانًا (§٢).
     *
     * @return array<string, mixed>
     */
    public function diagnosisSnapshotFor(Project $project): array
    {
        $result = $this->maturity->compute($project);

        return [
            'maturity_score' => $result['maturity_score'],
            'axes_active' => $result['axes_active'],
            'axes_total' => $result['axes_total'],
            'evidence_level' => $result['evidence_level'],

            /*
             * المحاور المقيسة وحدها تُمرَّر: تمرير محور بتغطية صفر يجعل
             * المولّد يبني توصية على فراغ ويعرضها بثقة الرقم.
             */
            'axes' => array_values(array_filter(
                $result['axes'],
                static fn (array $axis) => $axis['active'] === true,
            )),

            'gaps' => $this->fixes->build($project),
        ];
    }

    /**
     * هل يملك النشاط تشخيصًا يكفي لبناء خطة عليه؟
     *
     * خطة مبنية على صفر محاور هي بالضبط ما كانت المنصة تنتجه قبل هذا العمل:
     * نصّ عام من وصف المستخدم لنفسه. المنع هنا بنيوي لا إرشادي.
     */
    public function hasDiagnosisFor(Project $project): bool
    {
        return ($this->maturity->compute($project)['axes_active'] ?? 0) > 0;
    }

    /**
     * سبب المنع بلغة المستخدم، حين لا يكفي التشخيص.
     */
    public function blockedReason(): string
    {
        return 'لم يُقَس أي محور بعد. شغّل فحص الجاهزية أولًا لتُبنى خطتك على قياس نشاطك لا على وصفك له.';
    }

    /**
     * الفجوات مرتّبة، لتغذية مولّد المحتوى القديم بسياق حقيقي.
     *
     * @return array<int, array<string, mixed>>
     */
    public function prioritisedGapsFor(Project $project, ?Axis $axis = null): array
    {
        return $this->fixes->build($project, $axis === null ? null : [$axis]);
    }
}
