<?php

namespace App\Modules\Reporting;

use App\Models\Workspace;
use App\Modules\Diagnosis\MaturityAggregator;
use App\Modules\Diagnosis\ScoreHistory;
use App\Modules\Shared\Metrics\MetricKey;

/**
 * محفظة الوكالة: كل أنشطة المساحة بدرجاتها واتجاهها في جدول واحد.
 *
 * لماذا هي الأهم تجاريًّا (CLAUDE.md §٧)؟ لأن الوكالة ترى الاشتراك **تكلفة
 * اكتساب** لا مصروفًا: الجدول هو ما تفتحه صباح كل اثنين لتقرر على أي عميل
 * تصرف ساعات هذا الأسبوع. صاحب النشاط الواحد قد ينسى المنصة شهرًا؛ الوكالة
 * لا تستطيع.
 *
 * لا تحسب شيئًا جديدًا: تجمع ما حسبته `Diagnosis` وتقرأ ما قيّده `ScoreHistory`.
 * حساب ثانٍ هنا كان سيُنتج رقمًا يخالف ما يراه العميل في شاشته.
 */
class AgencyPortfolio
{
    public function __construct(
        private readonly MaturityAggregator $maturity,
        private readonly ScoreHistory $history,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Workspace $workspace): array
    {
        $rows = [];

        foreach ($workspace->projects()->with('profile')->get() as $project) {
            $result = $this->maturity->compute($project);
            $delta = $this->history->latestDelta($project);

            $rows[] = [
                'project' => ['id' => $project->id, 'slug' => $project->slug, 'name' => $project->name],
                'industry' => $project->industry,

                /*
                 * المحسوب من صفر محاور لا يُعرض رقمًا: صفٌّ بدرجة صفر في جدول
                 * محفظة يُقرأ «عميل فاشل» بينما معناه «عميل لم نقِسه بعد» —
                 * وهو حكم على الوكالة لا على عميلها.
                 */
                'measured' => $result['axes_active'] > 0,
                MetricKey::MATURITY_SCORE => $result['axes_active'] > 0
                    ? $result[MetricKey::MATURITY_SCORE]
                    : null,

                'axes_active' => $result['axes_active'],
                'axes_total' => $result['axes_total'],
                'score_coverage' => $result['score_coverage'],
                'evidence_level' => $result['evidence_level'],

                // الاتجاه لا يُعرض حين يكون سببه اتساع القياس لا تغيّر النشاط.
                'trend' => $this->trendOf($delta),
                'last_measured_at' => $delta['measured_at'] ?? null,
            ];
        }

        return [
            'workspace' => ['id' => $workspace->id, 'name' => $workspace->name],
            'projects' => $this->sorted($rows),
            'summary' => $this->summaryOf($rows),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $delta
     * @return array<string, mixed>
     */
    private function trendOf(?array $delta): array
    {
        if ($delta === null) {
            return ['direction' => 'unknown', 'delta' => null, 'reason' => 'قياس واحد فقط.'];
        }

        if ($delta['coverage_changed']) {
            return [
                'direction' => 'unknown',
                'delta' => null,
                'reason' => 'اتّسع ما نقيسه، فالفرق ليس تغيّرًا في النشاط.',
            ];
        }

        return [
            'direction' => match (true) {
                $delta['delta'] > 0 => 'up',
                $delta['delta'] < 0 => 'down',
                default => 'flat',
            },
            'delta' => $delta['delta'],
            'reason' => null,
        ];
    }

    /**
     * الأدنى درجةً أولًا: الجدول أداة قرار لا تقرير إنجاز.
     *
     * غير المقيس يسبق الجميع — عميل لم يُقَس هو أول ما يجب أن تفعله الوكالة،
     * وإخفاؤه في ذيل القائمة يجعله يُنسى شهورًا.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sorted(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            if ($a['measured'] !== $b['measured']) {
                return $a['measured'] ? 1 : -1;
            }

            return ($a[MetricKey::MATURITY_SCORE] ?? 0) <=> ($b[MetricKey::MATURITY_SCORE] ?? 0);
        });

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summaryOf(array $rows): array
    {
        $measured = array_values(array_filter($rows, static fn (array $row) => $row['measured']));
        $scores = array_column($measured, MetricKey::MATURITY_SCORE);

        return [
            'total' => count($rows),
            'measured' => count($measured),
            'unmeasured' => count($rows) - count($measured),

            // متوسط المقيس وحده، ومعه عدده: متوسط يشمل غير المقيس كذبٌ بالحساب.
            'average_score' => $scores === [] ? null : (int) round(array_sum($scores) / count($scores)),
            'declining' => count(array_filter(
                $rows,
                static fn (array $row) => ($row['trend']['direction'] ?? null) === 'down',
            )),
        ];
    }
}
