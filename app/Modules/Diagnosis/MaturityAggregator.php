<?php

namespace App\Modules\Diagnosis;

use App\Models\Project;
use App\Modules\Brain\BrainReader;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;

/**
 * درجة النضج التسويقي: المتوسط الموزون لدرجات المحاور المفعّلة.
 *
 * «المفعّلة» ليست تفصيلًا: المحور بلا مدخل واحد لم يُقَس، وإدخاله بصفر يخفض
 * الدرجة بلا سبب حقيقي فيطارد المستخدم رقمًا يعكس نقص بياناته لا حال نشاطه.
 * لذلك يخرج من البسط والمقام معًا، وتُعرض تغطيته صراحة (§٤.٣).
 */
class MaturityAggregator
{
    public function __construct(
        private readonly AxisScorer $scorer,
        private readonly BrainReader $brain,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compute(Project $project): array
    {
        $axes = $this->scorer->scoreAll($project);
        $active = array_values(array_filter($axes, fn (AxisScore $a) => $a->isActive()));

        $weighted = 0.0;
        $weights = 0.0;
        $levels = [];

        foreach ($active as $axisScore) {
            $weight = $axisScore->axis->weight();
            $weighted += $axisScore->score * $weight;
            $weights += $weight;
            $levels[] = $axisScore->evidenceLevel;
        }

        $score = $weights > 0.0 ? (int) round($weighted / $weights) : 0;

        return [
            MetricKey::MATURITY_SCORE => $score,
            'evidence_level' => EvidenceLevel::weakest($levels)->value,
            'axes_active' => count($active),
            'axes_total' => count($axes),

            /*
             * تغطية الدرجة نفسها: كم من المحاور دخلت الحساب. رقم بلا أساسه
             * يخالف §١٣ — «٦٢ من محورين» شيء، و«٦٢ من ثمانية» شيء آخر تمامًا.
             */
            'score_coverage' => count($axes) > 0 ? round(count($active) / count($axes), 4) : 0.0,
            'axes' => array_map(fn (AxisScore $a) => $a->toArray(), $axes),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * حساب الدرجة مع تجميد اللقطة التي حُسبت منها.
     *
     * بلا اللقطة يستحيل لاحقًا الإجابة على «لماذا كانت درجتي ٦٢ الشهر
     * الماضي؟»، لأن الحقائق تكون قد تغيّرت. هذا ما يجعل التنبيه صادقًا.
     *
     * @return array<string, mixed>
     */
    public function computeAndSnapshot(Project $project): array
    {
        $result = $this->compute($project);
        $snapshot = $this->brain->takeSnapshot($project);

        return $result + ['brain_snapshot_id' => $snapshot->id];
    }
}
