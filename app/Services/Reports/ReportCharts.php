<?php

namespace App\Services\Reports;

use App\Models\Report;

/**
 * بيانات الرسوم البيانية للتقرير — مصدر واحد حتمي (بلا ذكاء اصطناعي)
 * تستهلكه واجهة الويب وتطبيق Flutter وملف الـPDF معًا.
 */
class ReportCharts
{
    private const SEVERITY_META = [
        'critical' => ['label' => 'حرجة', 'color' => '#d92d20'],
        'high' => ['label' => 'عالية', 'color' => '#f79009'],
        'medium' => ['label' => 'متوسطة', 'color' => '#eab308'],
        'low' => ['label' => 'منخفضة', 'color' => '#98a2b3'],
    ];

    private const LEVELS = ['low', 'medium', 'high'];

    /**
     * @return array<string, mixed>
     */
    public function build(Report $report): array
    {
        $report->loadMissing(['findings.recommendations', 'toolRun.toolVersion.tool', 'project']);

        return [
            'score_gauge' => $this->scoreGauge($report),
            'score_history' => $this->scoreHistory($report),
            'severity_distribution' => $this->severityDistribution($report),
            'evidence_split' => $this->evidenceSplit($report),
            'impact_effort' => $this->impactEffort($report),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreGauge(Report $report): array
    {
        $score = (int) $report->score;

        return [
            'title' => 'الدرجة الكلية',
            'value' => $score,
            'max' => 100,
            'band' => $report->score_band,
            'color' => $this->scoreColor($score),
        ];
    }

    /**
     * تطور الدرجة عبر تقارير نفس الأداة لنفس المشروع — آخر 8 نقاط.
     *
     * @return array<string, mixed>|null
     */
    private function scoreHistory(Report $report): ?array
    {
        $siblings = Report::where('project_id', $report->project_id)
            ->whereNotNull('score')
            ->whereHas('toolRun.toolVersion', fn ($query) => $query->where('tool_id', $report->toolRun->toolVersion->tool_id))
            ->orderBy('created_at')
            ->get(['id', 'score', 'created_at'])
            ->take(-8);

        if ($siblings->count() < 2) {
            return null;
        }

        return [
            'title' => 'تطور الدرجة عبر التقارير',
            'points' => $siblings->map(fn (Report $sibling) => [
                'label' => $sibling->created_at?->locale('ar')->translatedFormat('j M') ?? '',
                'value' => (int) $sibling->score,
                'is_current' => $sibling->id === $report->id,
            ])->values()->all(),
            'max' => 100,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function severityDistribution(Report $report): ?array
    {
        if ($report->findings->isEmpty()) {
            return null;
        }

        $counts = $report->findings->countBy('severity');

        $items = collect(self::SEVERITY_META)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'count' => (int) ($counts[$key] ?? 0),
            ])
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values()
            ->all();

        return [
            'title' => 'النتائج حسب درجة الخطورة',
            'items' => $items,
            'total' => $report->findings->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evidenceSplit(Report $report): ?array
    {
        if ($report->findings->isEmpty()) {
            return null;
        }

        $assumptions = $report->findings->where('is_assumption', true)->count();
        $backed = $report->findings->count() - $assumptions;

        return [
            'title' => 'ما يستند إلى كلامك وما يحتاج تأكيدك',
            'items' => [
                ['key' => 'evidence', 'label' => 'مدعوم بدليل', 'color' => '#0f8a4d', 'count' => $backed],
                ['key' => 'assumption', 'label' => 'اجتهاد يحتاج تأكيدك', 'color' => '#b54708', 'count' => $assumptions],
            ],
            'total' => $report->findings->count(),
        ];
    }

    /**
     * خريطة التوصيات: الأثر مقابل الجهد — «ابدأ من هنا» = أثر عالٍ بجهد بسيط.
     *
     * @return array<string, mixed>|null
     */
    private function impactEffort(Report $report): ?array
    {
        $recommendations = $report->findings->flatMap->recommendations;

        if ($recommendations->isEmpty()) {
            return null;
        }

        $cells = [];

        foreach (self::LEVELS as $impact) {
            foreach (self::LEVELS as $effort) {
                $count = $recommendations
                    ->filter(fn ($recommendation) => ($recommendation->impact ?? 'medium') === $impact
                        && ($recommendation->effort ?? 'medium') === $effort)
                    ->count();

                $cells[] = ['impact' => $impact, 'effort' => $effort, 'count' => $count];
            }
        }

        return [
            'title' => 'خريطة التوصيات: الأثر مقابل الجهد',
            'impact_labels' => ['low' => 'أثر محدود', 'medium' => 'أثر متوسط', 'high' => 'أثر عالٍ'],
            'effort_labels' => ['low' => 'جهد بسيط', 'medium' => 'جهد متوسط', 'high' => 'جهد كبير'],
            'cells' => $cells,
            'quick_wins' => $recommendations
                ->filter(fn ($recommendation) => ($recommendation->impact ?? '') === 'high' && ($recommendation->effort ?? '') === 'low')
                ->count(),
            'total' => $recommendations->count(),
        ];
    }

    private function scoreColor(int $score): string
    {
        return match (true) {
            $score >= 70 => '#0f8a4d',
            $score >= 40 => '#f79009',
            default => '#d92d20',
        };
    }
}
