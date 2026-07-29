<?php

namespace App\Modules\Diagnosis;

use App\Models\Project;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Shared\Metrics\MetricKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * تاريخ `maturity_score` من أحداث التشخيص.
 *
 * لا اتصال شبكي ولا حساب جديد: يقرأ ما قُيِّد وقت الحساب. الدرجة التاريخية
 * التي يُعاد حسابها اليوم بقواعد اليوم ليست تاريخًا بل إعادة كتابة له.
 *
 * القاعدة التي يفرضها هذا الصنف على كل من يعرض اتجاهًا: **لا رسم بياني لأقل
 * من أربع نقاط** (§١٣). نقطتان تصنعان خطًّا صاعدًا أو هابطًا مهما كانت
 * ضوضاء القياس، والخط يُقرأ كاتجاه فيُتَّخذ عليه قرار.
 */
class ScoreHistory
{
    /** أقل عدد نقاط يجوز رسمه كاتجاه. */
    public const MIN_PLOTTABLE_POINTS = 4;

    /**
     * أقل فاصل بين نقطتين دوريتين.
     *
     * سبعة أيام لا رقم اعتباطي: أربع نقاط بهذا الفاصل = نافذة أربعة أسابيع،
     * وهي الحدّ الأدنى لأي مقارنة زمنية (§٤.٢). تقييد نقطة يوميًّا كان
     * سيُنتج «اتجاهًا» صالحًا للرسم بعد أربعة أيام — وهو ضوضاء بشكل خط.
     */
    public const MIN_INTERVAL_DAYS = 7;

    /**
     * نقاط الدرجة مرتَّبة من الأقدم إلى الأحدث.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function points(Project $project, int $limit = 24): Collection
    {
        return BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_DIAGNOSIS_SCORED)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (BrainEvent $event) => [
                MetricKey::MATURITY_SCORE => (int) ($event->body[MetricKey::MATURITY_SCORE] ?? 0),
                'score_coverage' => (float) ($event->body['score_coverage'] ?? 0.0),
                'axes_active' => (int) ($event->body['axes_active'] ?? 0),
                'evidence_level' => $event->body['evidence_level'] ?? null,
                'brain_snapshot_id' => $event->body['brain_snapshot_id'] ?? null,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ]);
    }

    /**
     * هل يجوز رسم هذا التاريخ بيانيًّا؟
     */
    public function isPlottable(Project $project): bool
    {
        return $this->points($project)->count() >= self::MIN_PLOTTABLE_POINTS;
    }

    /**
     * متى قُيِّدت آخر نقطة، أو null إن لم تُقيَّد نقطة قط.
     */
    private function lastRecordedAt(Project $project): ?Carbon
    {
        return BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_DIAGNOSIS_SCORED)
            ->orderByDesc('occurred_at')
            ->first()?->occurred_at;
    }

    /**
     * هل حان وقت تقييد نقطة دورية جديدة؟
     */
    public function isDueForPoint(Project $project): bool
    {
        $last = $this->lastRecordedAt($project);

        return $last === null || $last->diffInDays(now()) >= self::MIN_INTERVAL_DAYS;
    }

    /**
     * الفرق بين آخر قياسين، أو null إن لم يوجد قياسان.
     *
     * `coverage_changed` هو ما يمنع التنبيه الكاذب: درجة ارتفعت لأن محورًا
     * جديدًا دخل الحساب ليست تحسّنًا في النشاط بل اتساعًا في القياس، ولا
     * يجوز أن تصل صاحبها بوصفها تقدّمًا.
     *
     * @return array<string, mixed>|null
     */
    public function latestDelta(Project $project): ?array
    {
        $points = $this->points($project, 2);

        if ($points->count() < 2) {
            return null;
        }

        $previous = $points->first();
        $current = $points->last();

        return [
            'from' => $previous[MetricKey::MATURITY_SCORE],
            'to' => $current[MetricKey::MATURITY_SCORE],
            'delta' => $current[MetricKey::MATURITY_SCORE] - $previous[MetricKey::MATURITY_SCORE],
            'coverage_changed' => $previous['axes_active'] !== $current['axes_active'],
            'measured_at' => $current['occurred_at'],
            'previous_measured_at' => $previous['occurred_at'],
        ];
    }
}
