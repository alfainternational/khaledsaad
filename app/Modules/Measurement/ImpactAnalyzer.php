<?php

namespace App\Modules\Measurement;

use App\Models\Project;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Shared\Metrics\MetricKey;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * قياس الأثر المتقدم: هل تحرّكت إشارة مملوكة بعد إصلاح المستخدم؟
 *
 * المرجع: `docs/architecture/SPEC-advanced-impact.md`. يصل حدثَ التدخّل
 * (استبدال حقيقة = المستخدم غيّر شيئًا) بحركة درجة النضج في نافذتي ٤ أسابيع
 * قبله وبعده (§٤.٢).
 *
 * **لا شبكة هنا** (§١٤): يقرأ `brain_events` وحدها، فكل بطاقة أثر قابلة
 * لإعادة الإنتاج من لقطة (§٨). ولا نموذج لغوي: الحساب متوسطان وفرقهما.
 *
 * الإشارة اليوم `maturity_score` وحدها لأنها السلسلة الزمنية الوحيدة
 * المخزَّنة بنقاط كافية. البنية تقبل إشارات أخرى (GSC، زحف البوتات) بلا
 * تعديل جوهري — نقطة توسّع مفتوحة لا كود مرحلة لاحقة (§١١).
 */
class ImpactAnalyzer
{
    /** نصف نافذة المقارنة: ٤ أسابيع = ٢٨ يومًا (§٤.٢). */
    public const WINDOW_DAYS = 28;

    /**
     * بطاقات الأثر لكل تدخّل، الأحدث أولًا.
     *
     * `$asOf` يُمرَّر لا يُؤخذ من `now()` داخليًّا: نافذة «ما بعد» تكتمل
     * بمرور الوقت، وتثبيت لحظة القياس يجعل البطاقة قابلة لإعادة الإنتاج
     * ويجعل الاختبار حتميًّا.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forProject(Project $project, ?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ? CarbonImmutable::parse($asOf) : CarbonImmutable::now();

        $series = $this->maturitySeries($project);

        // بلا تاريخ إشارة لا أثر يُقاس: لا نقطة قبل ولا بعد.
        if ($series->isEmpty()) {
            return [];
        }

        return $this->interventions($project)
            ->map(fn (BrainEvent $event) => $this->window($event, $series, $asOf))
            ->filter(fn (ImpactWindow $window) => $this->isMeasurable($window, $asOf))
            ->map(fn (ImpactWindow $window) => $window->toArray())
            ->values()
            ->all();
    }

    /**
     * التدخّل يُقاس أثره فقط إذا مرّت نافذة «ما بعد» كاملة **واكتملت
     * النافذتان بنقاط**. تدخّلٌ عمره أسبوع لا أثر له بعد؛ يُطرح صامتًا لا
     * بصفر (§٤.٣) — البطاقة الناقصة تكذب أكثر من غيابها.
     */
    private function isMeasurable(ImpactWindow $window, CarbonInterface $asOf): bool
    {
        $afterWindowClosed = $window->interventionAt
            ->copy()
            ->addDays(self::WINDOW_DAYS)
            ->lessThanOrEqualTo($asOf);

        return $afterWindowClosed && $window->isComplete();
    }

    /**
     * @param  Collection<int, array{value: float, at: CarbonImmutable}>  $series
     */
    private function window(BrainEvent $event, Collection $series, CarbonInterface $asOf): ImpactWindow
    {
        $at = CarbonImmutable::parse($event->occurred_at);
        $beforeStart = $at->copy()->subDays(self::WINDOW_DAYS);
        $afterEnd = $at->copy()->addDays(self::WINDOW_DAYS);

        // «قبل» لا يشمل لحظة التدخّل، و«بعد» يبدأ منها: النقطة على الحدّ
        // تُنسب لما بعد الإصلاح لا لما قبله.
        $before = $series->filter(
            fn (array $point) => $point['at']->greaterThanOrEqualTo($beforeStart)
                && $point['at']->lessThan($at),
        );

        $after = $series->filter(
            fn (array $point) => $point['at']->greaterThanOrEqualTo($at)
                && $point['at']->lessThanOrEqualTo($afterEnd),
        );

        return new ImpactWindow(
            signal: MetricKey::MATURITY_SCORE,
            interventionLabel: $this->labelOf($event),
            interventionAt: $at,
            signalBefore: $this->average($before),
            signalAfter: $this->average($after),
            pointsBefore: $before->count(),
            pointsAfter: $after->count(),
        );
    }

    /**
     * @param  Collection<int, array{value: float, at: CarbonImmutable}>  $points
     */
    private function average(Collection $points): ?float
    {
        if ($points->isEmpty()) {
            return null;
        }

        return round($points->avg('value'), 2);
    }

    /**
     * سلسلة درجة النضج الزمنية من أحداث التسجيل.
     *
     * @return Collection<int, array{value: float, at: CarbonImmutable}>
     */
    private function maturitySeries(Project $project): Collection
    {
        return BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_DIAGNOSIS_SCORED)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (BrainEvent $event) => [
                'value' => (float) ($event->body[MetricKey::MATURITY_SCORE] ?? 0),
                'at' => CarbonImmutable::parse($event->occurred_at),
            ]);
    }

    /**
     * التدخّلات: استبدال حقيقة = المستخدم غيّر شيئًا عن نشاطه.
     *
     * @return Collection<int, BrainEvent>
     */
    private function interventions(Project $project): Collection
    {
        return BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_FACT_SUPERSEDED)
            ->orderByDesc('occurred_at')
            ->get();
    }

    private function labelOf(BrainEvent $event): string
    {
        $key = $event->body['key'] ?? null;

        // اسم الحقيقة المُصلَحة إن عُرف، وإلا وصف عام لا مفتاح خام.
        return is_string($key) && $key !== ''
            ? "حدّثت: {$key}"
            : 'حدّثت معلومة عن نشاطك';
    }
}
