<?php

namespace App\Modules\Diagnosis;

use App\Models\Project;
use App\Modules\Brain\BrainReader;
use App\Modules\Shared\Evidence\EvidenceLevel;

/**
 * حساب درجة محور واحد من لقطة الدماغ.
 *
 * حتمي بالكامل: نفس الحقائق تعطي نفس الدرجة دائمًا. لو حسبها نموذج لغوي
 * لتغيّرت بين تشغيلين بنفس المدخلات، وانهارت المقارنة الزمنية — وعليها يقوم
 * التنبيه، وهو المخرج المتكرر الوحيد (§٦).
 *
 * لا اتصال شبكي هنا ولا في أي مكان من هذه الوحدة. الجمع مسؤولية `AiReadiness`
 * و`OwnedAssets`، وهذه الوحدة تقرأ ما كتبوه.
 *
 * القواعد الأربع مأخوذة من `DeterministicScorer` القديم بعد ترقيتها من درجة
 * أداة إلى درجة محور: نفس المنطق، ومصدر مختلف، ومخرج بمفردات §١٢.
 */
class AxisScorer
{
    public function __construct(
        private readonly BrainReader $brain,
        private readonly AxisRegistry $registry,
        private readonly InputFitnessReader $fitness,
    ) {}

    public function score(Project $project, Axis $axis): AxisScore
    {
        $inputs = $this->registry->inputsFor($axis);
        $facts = $this->brain->facts($project);
        $fitness = $this->fitness->forProject($project);

        $totalWeight = 0.0;
        $earned = 0.0;
        $known = 0;
        $breakdown = [];
        $gaps = [];
        $levels = [];
        $fitnessScores = [];

        foreach ($inputs as $input) {
            $weight = (float) ($input['weight'] ?? 1);
            $fact = $facts->get($input['key']);
            $value = $fact?->value_json['value'] ?? null;

            $totalWeight += $weight;

            if ($fact === null) {
                // الغائب يُعلَن ولا يُقدَّر: يخفض التغطية ويظهر في الفجوات.
                $gaps[] = $input['label'];
                $breakdown[] = [
                    'label' => $input['label'],
                    'weight' => $weight,
                    'factor' => 0.0,
                    'points' => 0.0,
                    'known' => false,
                ];

                continue;
            }

            $known++;
            $levels[] = $fact->evidence_level;
            $factor = $this->factor($input, $value);

            /*
             * معامل الكفاية: «أجاب» لا يساوي «أجاب بما يكفي».
             *
             * قبل هذا كان أي نصّ غير فارغ يُحسب مدخلًا كاملًا، فتستوي «الجميع»
             * بتعريف ثلاث شرائح بأرقامها. المحور كان يقيس أن المستخدم كتب شيئًا
             * لا جودة ما كتبه، فيخرج التقرير مطمئنًا على أضعف ما عنده.
             *
             * القياس نفسه يجري في `Intake` ويُقرأ هنا من قاعدة البيانات: الدرجة
             * تبقى قابلة لإعادة الإنتاج من لقطة (§٨).
             */
            $quality = $fitness[$input['key']] ?? null;

            if ($quality !== null) {
                $factor *= $quality->factor();
                $fitnessScores[] = $quality->score;

                if (! $quality->isSufficient()) {
                    // الفجوة تُسمّى بما ينقص الإجابة، لا بغياب المدخل — المدخل موجود.
                    $gaps[] = $input['label'].' — '.implode('، ', $quality->gaps ?: ['يحتاج تحديدًا أكثر']);
                }
            }

            $earned += $weight * $factor;

            if ($factor < 1.0) {
                $gaps[] = $input['label'];
            }

            $breakdown[] = [
                'label' => $input['label'],
                'weight' => $weight,
                'factor' => round($factor, 2),
                'points' => round($weight * $factor, 2),
                'known' => true,
                // درجة كفاية هذا المدخل بعينه، أو null لما لا يُقاس (أسئلة الاختيار).
                'fitness' => $quality?->score,
            ];
        }

        $count = count($inputs);

        return new AxisScore(
            axis: $axis,
            score: $totalWeight > 0.0 ? (int) round(max(0.0, min(1.0, $earned / $totalWeight)) * 100) : 0,
            coverage: $count > 0 ? round($known / $count, 4) : 0.0,
            evidenceLevel: $this->levelFor($axis, $levels, $known),
            breakdown: $breakdown,
            gaps: array_values(array_unique($gaps)),
            /*
             * متوسط كفاية المدخلات المفتوحة في هذا المحور، أو null حين لا يحمل
             * المحور مدخلًا مفتوحًا واحدًا. صفرٌ هنا كان سيُقرأ «مدخلاته سيئة»
             * بينما المعنى «لا مدخل يُقاس» — وهو الفرق الذي تحرسه §٤.٣.
             */
            inputFitness: $fitnessScores === []
                ? null
                : (int) round(array_sum($fitnessScores) / count($fitnessScores)),
        );
    }

    /**
     * @return array<int, AxisScore>
     */
    public function scoreAll(Project $project): array
    {
        return array_map(
            fn (Axis $axis) => $this->score($project, $axis),
            Axis::ordered(),
        );
    }

    /**
     * مستوى دليل المحور: أضعف مدخلاته، محدودًا بسقف المحور نفسه.
     *
     * السقف يمنع الترقية من الباب الخلفي: لو كتبت وحدة ما حقيقة `measured`
     * تحت مفتاح يخصّ محورًا استنتاجيًّا، يظل المحور `inferred` لأن مصدره
     * بطبيعته كلام صاحب النشاط عن نفسه (§١٥).
     *
     * @param  array<int, EvidenceLevel>  $levels
     */
    private function levelFor(Axis $axis, array $levels, int $known): EvidenceLevel
    {
        if ($known === 0) {
            return EvidenceLevel::Inferred;
        }

        $observed = EvidenceLevel::weakest($levels);
        $ceiling = $axis->ceiling();

        return $observed->strength() > $ceiling->strength() ? $ceiling : $observed;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function factor(array $input, mixed $value): float
    {
        return match ($input['rule'] ?? 'present') {
            'map' => (float) ($input['map'][$this->mapKey($value)] ?? 0.0),
            'count' => $this->countFactor($value, (int) ($input['target'] ?? 1)),
            'range' => $this->rangeFactor($value, $input),
            default => $this->presentFactor($value),
        };
    }

    private function mapKey(mixed $value): string
    {
        return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
    }

    private function presentFactor(mixed $value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_array($value)) {
            return $value === [] ? 0.0 : 1.0;
        }

        return trim((string) $value) === '' ? 0.0 : 1.0;
    }

    private function countFactor(mixed $value, int $target): float
    {
        $count = is_array($value) ? count($value) : (int) $value;

        return $target <= 0 ? 0.0 : min(1.0, $count / $target);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function rangeFactor(mixed $value, array $input): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $min = (float) ($input['min'] ?? 0);
        $max = (float) ($input['max'] ?? 100);

        if ($max <= $min) {
            return 0.0;
        }

        return max(0.0, min(1.0, ((float) $value - $min) / ($max - $min)));
    }
}
