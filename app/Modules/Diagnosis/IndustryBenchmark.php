<?php

namespace App\Modules\Diagnosis;

use App\Models\Project;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Shared\Metrics\MetricKey;
use App\Modules\Shared\Sectors\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * موقع النشاط من متوسط قطاعه.
 *
 * يجيب على «مشكلتي في الاستراتيجية أم التنفيذ» بإطار مفقود بلا مقارنة: درجة
 * ٥٨ تعني شيئًا مختلفًا تمامًا إن كان متوسط القطاع ٤٠ أو ٧٥.
 *
 * **حدّ العيّنة معلن ومفروض.** متوسط من مشروعين ليس معيار قطاع بل صدفة، وعرضه
 * يمنح رقمًا عشوائيًّا سلطةَ مرجع. تحت الحدّ لا يُعرض شيء ويُقال السبب — لا
 * يُعرض متوسط «تقريبي» (§٤.٣).
 *
 * يقرأ من قاعدة البيانات وحدها: كل نقطة مصدرها حدث تشخيص قُيِّد وقت حسابه.
 */
class IndustryBenchmark
{
    /**
     * أقل عدد أنشطة يصير معه المتوسط معيارًا.
     *
     * خمسة حدّ أدنى متواضع مقصود: أقلّ منه تتحكّم فيه حالة واحدة شاذة، وأكثر
     * منه يمنع القطاعات الصغيرة من أي مقارنة إطلاقًا.
     */
    public const MIN_SAMPLE = 5;

    /**
     * @return array<string, mixed>
     */
    public function for(Project $project): array
    {
        /*
         * القطاع المعلن يجمع الأقران (مواصفة التخصص القطاعي): «التعليم»
         * مجموعة واحدة مهما اختلفت صياغات أصحابها لمجالهم. النص الحر يبقى
         * احتياطًا للمشاريع التي سبقت المنتقي — تفتّته عيب موروث معلن.
         */
        $declared = Sector::isSpecialized($project->sector);
        $industry = $declared ? Sector::label($project->sector) : $project->industry;

        if (blank($industry)) {
            return $this->unavailable(__('قطاع النشاط غير محدَّد، فلا مجموعة يُقارَن بها.'));
        }

        $scores = $declared
            ? $this->latestScoresInSector($project->sector, exceptProject: $project->id)
            : $this->latestScoresIn($industry, exceptProject: $project->id);

        if (count($scores) < self::MIN_SAMPLE) {
            return $this->unavailable(sprintf(
                __('عدد الأنشطة المقيسة في «%s» %d، والمقارنة تبدأ عند %d.'),
                $industry,
                count($scores),
                self::MIN_SAMPLE,
            ));
        }

        $average = (int) round(array_sum($scores) / count($scores));
        $mine = $this->latestScoreOf($project);

        return [
            'available' => true,
            'industry' => $industry,
            'sample_size' => count($scores),
            'industry_average' => $average,
            MetricKey::MATURITY_SCORE => $mine,

            /*
             * الفرق يُحسب هنا لا في القالب (§١٤). ولو لم تُقَس درجة النشاط بعد
             * فلا فرق: المقارنة تحتاج طرفين.
             */
            'delta' => $mine === null ? null : $mine - $average,
            'percentile' => $mine === null ? null : $this->percentileOf($mine, $scores),
        ];
    }

    /**
     * @param  array<int, int>  $scores
     */
    private function percentileOf(int $mine, array $scores): int
    {
        $below = count(array_filter($scores, static fn (int $score) => $score < $mine));

        return (int) round($below / count($scores) * 100);
    }

    /**
     * آخر درجة مقيَّدة لكل نشاط في القطاع.
     *
     * «آخر درجة لكل نشاط» لا «كل الدرجات»: نشاط قِيس عشر مرات كان سيزن عشرة
     * أضعاف نشاط قِيس مرة، فيصير المتوسط وصفًا لمن يستخدم المنصة أكثر لا
     * لحال القطاع.
     *
     * @return array<int, int>
     */
    private function latestScoresIn(string $industry, int $exceptProject): array
    {
        return $this->latestScoresOfPeers(
            Project::query()->where('industry', $industry)->whereKeyNot($exceptProject),
        );
    }

    /**
     * أقران القطاع المعلن: العمود القانوني يجمع ما كان النص الحر يفتّته.
     *
     * @return array<int, int>
     */
    private function latestScoresInSector(string $sector, int $exceptProject): array
    {
        return $this->latestScoresOfPeers(
            Project::query()->where('sector', $sector)->whereKeyNot($exceptProject),
        );
    }

    /**
     * @param  Builder<Project>  $peers
     * @return array<int, int>
     */
    private function latestScoresOfPeers($peers): array
    {
        $latest = BrainEvent::query()
            ->selectRaw('MAX(id) as id')
            ->where('type', BrainEvent::TYPE_DIAGNOSIS_SCORED)
            ->whereIn('project_id', $peers->select('id'))
            ->groupBy('project_id');

        return BrainEvent::query()
            ->whereIn('id', DB::query()->fromSub($latest, 'latest')->select('id'))
            ->get()
            ->map(fn (BrainEvent $event) => (int) ($event->body[MetricKey::MATURITY_SCORE] ?? 0))
            ->filter(fn (int $score) => $score > 0)
            ->values()
            ->all();
    }

    private function latestScoreOf(Project $project): ?int
    {
        $event = BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_DIAGNOSIS_SCORED)
            ->orderByDesc('occurred_at')
            ->first();

        $score = $event?->body[MetricKey::MATURITY_SCORE] ?? null;

        return $score === null ? null : (int) $score;
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'sample_size' => 0];
    }
}
