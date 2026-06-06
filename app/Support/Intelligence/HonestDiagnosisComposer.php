<?php

namespace App\Support\Intelligence;

class HonestDiagnosisComposer
{
    /**
     * @param  array<string, int>  $scores
     * @param  array<int, array<string, mixed>>  $projectFindings
     * @param  array<int, array<string, mixed>>  $competitors
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<int, array<string, mixed>>  $trend
     * @return array<string, mixed>
     */
    public function compose(
        array $scores,
        array $projectFindings,
        array $competitors,
        array $contacts,
        array $trend = [],
        array $evidence = [],
    ): array {
        $trustedProblems = collect($projectFindings)
            ->filter(fn (array $finding): bool => (float) ($finding['confidence'] ?? 0) >= 0.65)
            ->sortByDesc(fn (array $finding): int => (int) ($finding['score_impact'] ?? 0))
            ->take(5)
            ->values()
            ->all();

        $status = (string) ($evidence['status'] ?? 'partial');
        $orderedProblems = $trustedProblems !== [] ? $trustedProblems : collect($projectFindings)
            ->sortByDesc(fn (array $finding): int => (int) ($finding['score_impact'] ?? 0))
            ->take($status === 'insufficient' ? 2 : 5)
            ->values()
            ->all();

        $problems = array_map(fn (array $finding): string => (string) $finding['title'], $orderedProblems);
        $opportunities = array_values(array_filter(array_map(
            fn (array $finding): string => (string) ($finding['recommendation'] ?? ''),
            $orderedProblems,
        )));

        if ($status === 'insufficient') {
            return [
                'honest_diagnosis' => array_values(array_filter(array_merge(
                    ['لا توجد تغطية كافية لبناء diagnosis نهائي أو action plan واسع؛ القراءة الحالية أولية فقط.'],
                    array_map(
                        fn (string $warning): string => 'قيد التحليل: '.$warning,
                        array_slice($evidence['warnings'] ?? [], 0, 2),
                    ),
                ))),
                'top_5_problems' => $problems,
                'top_5_opportunities' => $opportunities,
                'priority_actions' => [
                    'quick_wins_7_days' => $this->timelineActions($orderedProblems, 2),
                    'improvements_30_days' => [],
                    'strategic_90_days' => [],
                ],
                'competitor_snapshot' => $this->competitorSnapshot($competitors, $evidence),
                'official_contacts' => $contacts,
                'monitoring_trend' => $trend,
            ];
        }

        $honestDiagnosis = array_values(array_filter([
            ($scores['conversion'] ?? 0) < 60 ? 'التحويل أضعف من بقية الطبقات، وهذا يعني أن المشكلة ليست في الظهور وحده بل في مسار القرار.' : null,
            ($scores['trust'] ?? 0) < 65 ? 'إشارات الثقة لا تزال دون المستوى الذي يطمئن الزائر أو العميل المحتمل.' : null,
            ($scores['seo'] ?? 0) < 65 ? 'الأساس SEO ما زال عملياً لكنه غير منضبط بما يكفي للفهرسة والوضوح.' : null,
            ($scores['social'] ?? 0) < 60 ? 'الحضور الاجتماعي موجود أو قابل للقياس لكن رسالته أو ترابطه مع الموقع غير ناضجين بعد.' : null,
            count($contacts) === 0 ? 'قنوات التواصل الرسمية ليست مرتبة بما يكفي لتغذية المبيعات أو المتابعة المنظمة.' : null,
            $competitors !== [] ? $this->competitorLeadLine($competitors) : null,
            $status === 'partial' && ! empty($evidence['warnings']) ? 'هذه القراءة جزئية وتحتاج استكمال بعض المدخلات قبل اعتمادها كقرار نهائي.' : null,
        ]));

        return [
            'honest_diagnosis' => $honestDiagnosis,
            'top_5_problems' => $problems,
            'top_5_opportunities' => $opportunities,
            'priority_actions' => [
                'quick_wins_7_days' => $this->timelineActions($orderedProblems, 2),
                'improvements_30_days' => $this->timelineActions($orderedProblems, 4),
                'strategic_90_days' => $this->strategicActions($scores, $competitors, $orderedProblems, $status),
            ],
            'competitor_snapshot' => $this->competitorSnapshot($competitors, $evidence),
            'official_contacts' => $contacts,
            'monitoring_trend' => $trend,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderedProblems
     * @return array<int, string>
     */
    private function timelineActions(array $orderedProblems, int $take): array
    {
        return collect($orderedProblems)
            ->take($take)
            ->map(fn (array $finding): string => (string) ($finding['recommendation'] ?? $finding['title']))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $scores
     * @param  array<int, array<string, mixed>>  $competitors
     * @param  array<int, array<string, mixed>>  $orderedProblems
     * @return array<int, string>
     */
    private function strategicActions(array $scores, array $competitors, array $orderedProblems, string $status): array
    {
        if ($status !== 'verified') {
            return collect($orderedProblems)
                ->skip(2)
                ->take(3)
                ->map(fn (array $finding): string => (string) ($finding['recommendation'] ?? $finding['title']))
                ->filter()
                ->values()
                ->all();
        }

        $actions = [];

        if (($scores['ai_visibility'] ?? 0) < 70) {
            $actions[] = 'ابنِ صفحات تعريف وخدمة وFAQ أوضح قابلة للاقتباس حتى تتحسن جاهزية العلامة في بيئات الإجابة.';
        }

        if (($scores['ads_readiness'] ?? 0) < 70) {
            $actions[] = 'رتّب صفحة القرار والـ CTA والتتبع قبل أي توسيع إعلاني جاد.';
        }

        if ($competitors !== []) {
            $actions[] = 'حوّل الفجوة التي تركها المنافسون إلى رسالة تمركز ثابتة بدلاً من تقليد شكلهم أو كثافة نشرهم.';
        }

        if ($actions === []) {
            $actions[] = 'النظام الأساسي جيد؛ الآن اجعل التحسينات القادمة مرتبطة بالتحويل والتميّز لا بالتجميل.';
        }

        return $actions;
    }

    /**
     * @param  array<int, array<string, mixed>>  $competitors
     * @return array<string, mixed>
     */
    private function competitorSnapshot(array $competitors, array $evidence = []): array
    {
        if ($competitors === []) {
            return [
                'leaders' => [],
                'summary' => $evidence['competitor_summary'] ?? 'لا توجد بيانات منافسين كافية حالياً.',
            ];
        }

        $sorted = collect($competitors)->sortByDesc('executive_score')->values();

        return [
            'leaders' => $sorted->take(3)->all(),
            'summary' => $this->competitorLeadLine($sorted->all()) ?: 'تم جمع مقارنات منافسين أولية.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $competitors
     */
    private function competitorLeadLine(array $competitors): ?string
    {
        $leader = collect($competitors)->sortByDesc('executive_score')->first();
        if (! is_array($leader)) {
            return null;
        }

        return 'المنافس الأوضح حالياً هو '.($leader['label'] ?? 'أحد المنافسين').' بدرجة تقريبية '.($leader['executive_score'] ?? 0).'/100، ما يحدد لك سقفاً أوضح للمقارنة والتميّز.';
    }
}
