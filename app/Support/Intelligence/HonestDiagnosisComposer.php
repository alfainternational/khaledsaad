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
                    ['المعلومات الحالية لا تكفي لإعطائك تحليلاً نهائياً أو خطة عمل واسعة؛ ما تراه الآن قراءة أولية فقط.'],
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
            ($scores['conversion'] ?? 0) < 60 ? 'تحويل الزائر إلى عميل أضعف من باقي الجوانب، وهذا يعني أن المشكلة ليست في الظهور وحده بل في طريقة دفع الزائر لاتخاذ القرار.' : null,
            ($scores['trust'] ?? 0) < 65 ? 'إشارات الثقة ما زالت أقل من المستوى الذي يطمئن الزائر أو العميل المحتمل.' : null,
            ($scores['seo'] ?? 0) < 65 ? 'ظهورك في البحث ما زال عملياً لكنه غير منظّم بما يكفي للوضوح وسهولة العثور عليك.' : null,
            ($scores['social'] ?? 0) < 60 ? 'حضورك على التواصل الاجتماعي موجود لكن رسالته أو ربطه بموقعك لم يكتملا بعد.' : null,
            count($contacts) === 0 ? 'قنوات التواصل الرسمية ليست مرتبة بما يكفي لاستقبال العملاء أو متابعتهم بانتظام.' : null,
            $competitors !== [] ? $this->competitorLeadLine($competitors) : null,
            $status === 'partial' && ! empty($evidence['warnings']) ? 'هذه قراءة جزئية وتحتاج إلى استكمال بعض البيانات قبل اعتمادها كقرار نهائي.' : null,
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
            $actions[] = 'جهّز صفحات تعريف وخدمات وأسئلة شائعة واضحة وسهلة الاقتباس حتى يتحسّن ظهورك في محركات الإجابة الذكية.';
        }

        if (($scores['ads_readiness'] ?? 0) < 70) {
            $actions[] = 'رتّب صفحة اتخاذ القرار وزر الإجراء ووسيلة قياس النتائج قبل أي توسّع إعلاني جاد.';
        }

        if ($competitors !== []) {
            $actions[] = 'حوّل النقص الذي تركه منافسوك إلى رسالة تميّز ثابتة بدلاً من تقليد شكلهم أو كثرة منشوراتهم.';
        }

        if ($actions === []) {
            $actions[] = 'الأساس جيد؛ اجعل تحسيناتك القادمة مرتبطة بتحويل الزائر إلى عميل وبتميّزك، لا بالتجميل فقط.';
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
                'summary' => $evidence['competitor_summary'] ?? 'لا توجد بيانات كافية عن منافسيك حالياً.',
            ];
        }

        $sorted = collect($competitors)->sortByDesc('executive_score')->values();

        return [
            'leaders' => $sorted->take(3)->all(),
            'summary' => $this->competitorLeadLine($sorted->all()) ?: 'جمعنا مقارنات أولية مع منافسيك.',
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

        return 'أبرز منافسيك حالياً هو '.($leader['label'] ?? 'أحد المنافسين').' بدرجة تقريبية '.($leader['executive_score'] ?? 0).'/100، وهذا يعطيك سقفاً أوضح للمقارنة والتميّز.';
    }
}
