<?php

namespace App\Support\Presentation;

use App\Models\Report;
use App\Modules\Competitors\CompetitorRegistry;
use App\Modules\Reporting\ReportCharts;

class ReportPresenter
{
    public function __construct(
        private readonly CompetitorRegistry $competitors,
        private readonly ReportCharts $charts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function full(Report $report): array
    {
        $report->loadMissing(['sections', 'findings.recommendations.task', 'findings.recommendations.objective', 'findings.recommendations.metricObjective', 'findings.recommendations.template.objective', 'scoringItems', 'humanTraces', 'toolRun.toolVersion.tool', 'project']);

        // قائمة المنافسين تُقرأ حيّة لا من لقطة التقرير: تأكيد/استبعاد يظهر فورًا.
        $competitorView = $this->competitors->forReport($report->project);
        $confirmation = collect($report->assumptions ?? [])->first();
        if (is_array($confirmation)) {
            $confirmation = $confirmation['text'] ?? $confirmation['title'] ?? null;
        }
        if (! is_string($confirmation) || trim($confirmation) === '') {
            $confirmation = $report->findings->firstWhere('is_assumption', true)?->title;
        }

        return [
            'id' => $report->id,
            'title' => $report->title,
            // لغة نصّ التقرير — تُعلَن للقارئ حين تختلف عن لغة واجهته،
            // وتحدّد اتجاه صفحة الـPDF وخطّها.
            'locale' => $report->locale ?: 'ar',
            'status' => $report->status,
            'score' => $report->score,
            'score_equation' => [
                'raw' => (float) ($report->score_raw ?? $report->scoringItems->sum('points')),
                'max' => (float) ($report->score_max ?? $report->scoringItems->sum('weight')),
                'value' => (float) $report->score,
            ],
            'score_band' => $report->score_band,
            'summary' => $report->summary,
            'executive_summary' => [
                'current_state' => $report->summary,
                'top_issues' => $report->findings
                    ->sortBy('sort_order')
                    ->take(3)
                    ->pluck('title')
                    ->values()
                    ->all(),
                'this_week' => $report->next_step,
                'needs_confirmation' => $confirmation,
            ],
            // توثيق أن التقرير رُوجع بشريًا: بيان صادق موجز، لا لافتة تمدح.
            // الإحساس البشري يجيء من نص التقرير نفسه (تعليمات المُراجِع)، لا
            // من جملة تدّعي أنها مكتوبة بيد.
            'is_manually_reviewed' => in_array($report->provenance, ['signed', 'hybrid'], true),
            'reviewed_at' => $report->reviewed_at?->locale('ar')->translatedFormat('j F Y'),
            'reviewer_name' => in_array($report->provenance, ['signed', 'hybrid'], true) ? config('brand.name', 'خالد سعد') : null,
            'provenance_type' => $report->provenance ?: 'automated',
            'provenance_label' => \App\Modules\Reporting\Publication\Provenance::tryFrom($report->provenance ?: 'automated')?->label(),
            'validation_status' => $report->validation_status,
            'schema_version' => $report->schema_version,
            'human_traces' => $report->humanTraces->map->only(['type', 'body', 'created_at'])->values()->all(),
            'assumptions' => $report->assumptions ?? [],
            /*
             * الفجوات المفتوحة وحدها: ما سُدَّ منها يبقى في العمود ولا يُعرض.
             * الويب والتطبيق يقرآن هذا المفتاح نفسه، فلا يفترق البابان.
             */
            'open_gaps' => collect($report->declared_gaps ?? [])
                ->filter(fn ($gap) => is_array($gap) && ($gap['answered_at'] ?? null) === null)
                ->map(fn (array $gap) => [
                    'key' => (string) ($gap['key'] ?? ''),
                    'label' => (string) ($gap['label'] ?? ''),
                    'why' => $gap['why'] ?? null,
                ])
                ->values()
                ->all(),
            'next_step' => $report->next_step,
            'project' => ['name' => $report->project->name, 'slug' => $report->project->slug],
            // يحتاجه زر إعادة طلب التحليل الموسع في الويب والتطبيق معًا.
            'run_uuid' => $report->toolRun->uuid,
            'can_request_deeper' => $report->findings->isEmpty(),
            'tool' => [
                'key' => $report->toolRun->toolVersion->tool->key,
                'title' => $report->toolRun->toolVersion->tool->title,
            ],
            /*
             * بصمة اللحظة دون كشف المزود.
             * اسم النموذج يبقى في ai_usage_records ولوحة الإدارة: العميل اشترى
             * نتيجة، لا اشتراكًا في مزود معين، وكشفه يضعف قيمة المنصة ويقيّدنا
             * إن غيّرنا المزود لاحقًا.
             */
            'provenance' => [
                'tool_version' => $report->tool_version,
                'generated_at' => $report->created_at?->toIso8601String(),
                'run_status' => $report->toolRun->status,
            ],
            'sections' => $report->sections->map(fn ($section) => [
                'key' => $section->key,
                'title' => $section->title,
                // قسم المنافسين يأخذ القائمة الحيّة بدل لقطة لحظة التركيب.
                'content' => $section->key === 'competitors'
                    ? [...$section->content_json, 'confirmed' => $competitorView['confirmed'], 'candidates' => $competitorView['candidates'], 'prompt_local' => ! $competitorView['has_local']]
                    : $section->content_json,
            ])->values()->all(),
            'findings' => $report->findings->map(fn ($finding) => [
                'id' => $finding->id,
                'title' => $finding->title,
                'description' => $finding->description,
                'category' => $finding->category,
                'severity' => $finding->severity,
                'severity_label' => $finding->severityLabel(),
                'evidence' => $finding->evidence,
                'confidence' => $finding->confidence,
                'is_assumption' => $finding->is_assumption,
                // التسمية الظاهرة للمستخدم: الفصل بين الدليل والافتراض ليس تفصيلًا تقنيًا.
                'basis_label' => $finding->is_assumption ? __('افتراض') : __('مدعوم بدليل'),
                'recommendations' => $finding->recommendations->map(fn ($recommendation) => [
                    'id' => $recommendation->id,
                    'title' => $recommendation->title,
                    'description' => $recommendation->description,
                    'objective_id' => $recommendation->objective?->slug,
                    'deliverable' => $recommendation->deliverable,
                    'done_when' => $recommendation->done_when,
                    'first_five_minutes' => $recommendation->first_five_minutes,
                    'expected_failure' => $recommendation->expected_failure,
                    'duration_days' => $recommendation->duration_days,
                    'metric' => [
                        'label' => $recommendation->kpi_hint,
                        'objective_id' => $recommendation->metricObjective?->slug,
                    ],
                    'template' => $recommendation->template_payload ?: ($recommendation->template ? [
                        'objective_id' => $recommendation->template->objective?->slug,
                        'kind' => $recommendation->template->kind,
                        'title' => $recommendation->template->title,
                        'blocks' => $recommendation->template->body['blocks'] ?? [],
                        'tips' => $recommendation->template->body['tips'] ?? [],
                        'is_hypothesis' => (bool) $recommendation->template->is_hypothesis,
                    ] : null),
                    'degraded' => (bool) $recommendation->degraded,
                    'degrade_reason' => $recommendation->degrade_reason,
                    'root_cause' => $recommendation->root_cause,
                    'commercial_impact' => $recommendation->commercial_impact,
                    'action_steps' => $recommendation->action_steps ?? [],
                    // المثال التطبيقي: النص الذي ينسخه ويستعمله. مصدره يمرّ معه
                    // لأن مثال النموذج ومثال الأرضية الحتمية لا يُقرآن بنفس الثقة.
                    'worked_example' => $recommendation->worked_example,
                    'example_source' => $recommendation->example_source,
                    'owner_role' => $recommendation->owner_role,
                    'resources' => $recommendation->resources ?? [],
                    'timeframe' => $recommendation->timeframe,
                    'dependencies' => $recommendation->dependencies ?? [],
                    'impact' => $recommendation->impact,
                    'impact_label' => $recommendation->impactLabel(),
                    'effort' => $recommendation->effort,
                    'effort_label' => $recommendation->effortLabel(),
                    'priority' => $recommendation->priority,
                    'kpi_hint' => $recommendation->kpi_hint,
                    'kpi_definition' => $recommendation->kpi_definition,
                    'kpi_source' => $recommendation->kpi_source,
                    'baseline' => $recommendation->baseline,
                    'target' => $recommendation->target,
                    'missing_baseline_reason' => $recommendation->missing_baseline_reason,
                    'success_condition' => $recommendation->success_condition,
                    'stop_condition' => $recommendation->stop_condition,
                    'risks' => $recommendation->risks ?? [],
                    'confidence' => $recommendation->confidence,
                    'task_id' => $recommendation->task?->id,
                ])->values()->all(),
            ])->values()->all(),
            'counts' => [
                'findings' => $report->findings->count(),
                'evidence_backed' => $report->findings->where('is_assumption', false)->count(),
                'assumptions' => $report->findings->where('is_assumption', true)->count(),
            ],
            // بيانات الرسوم البيانية: مصدر واحد للويب والتطبيق والـPDF.
            'charts' => $this->charts->build($report),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function card(Report $report): array
    {
        $report->loadMissing('toolRun.toolVersion.tool');

        return [
            'id' => $report->id,
            'title' => $report->title,
            'score' => $report->score,
            'score_band' => $report->score_band,
            'status' => $report->status,
            'created_at' => $report->created_at?->toIso8601String(),
            'tool' => [
                'key' => $report->toolRun->toolVersion->tool->key,
                'title' => $report->toolRun->toolVersion->tool->title,
            ],
        ];
    }

    /**
     * التقرير السابق لنفس الأداة — أساس المقارنة الزمنية.
     *
     * مشتركة بين الويب والـAPI وملف الطباعة كي تُقاس المقارنة بالطريقة نفسها.
     */
    public function previousFor(Report $report): ?Report
    {
        return Report::where('project_id', $report->project_id)
            ->where('id', '!=', $report->id)
            ->whereHas('toolRun', fn ($query) => $query->where('tool_version_id', $report->toolRun->tool_version_id))
            ->where('created_at', '<', $report->created_at)
            ->latest('created_at')
            ->first();
    }

    /**
     * مقارنة تقريرين لنفس الأداة — القيمة التي لا تعطيها أداة تعمل مرة واحدة.
     *
     * @return array<string, mixed>|null
     */
    public function comparison(Report $current, ?Report $previous): ?array
    {
        if ($previous === null) {
            return null;
        }

        $delta = (int) $current->score - (int) $previous->score;

        return array_merge(
            $this->deltaBlock($previous, $current, $delta),
            $this->gapsDiff($previous, $current),
        );
    }

    /**
     * فرق الفجوات بين تقريرين: ما أُغلق وما ظهر — بعنوان النتيجة المطبَّع.
     * الدرجة تقول «كم تحركت»، وهذا يقول «ماذا تغيّر فعلًا» — وهو ما يقود القرار.
     *
     * @return array{closed_gaps: array<int, string>, new_gaps: array<int, string>}
     */
    private function gapsDiff(Report $previous, Report $current): array
    {
        $normalize = fn (string $title) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($title)) ?? $title);

        $previousTitles = $previous->findings->pluck('title')->keyBy($normalize);
        $currentTitles = $current->findings->pluck('title')->keyBy($normalize);

        return [
            'closed_gaps' => $previousTitles->diffKeys($currentTitles)->take(5)->values()->all(),
            'new_gaps' => $currentTitles->diffKeys($previousTitles)->take(5)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deltaBlock(Report $previous, Report $current, int $delta): array
    {
        return [
            'previous_score' => $previous->score,
            'current_score' => $current->score,
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'label' => match (true) {
                $delta > 0 => "درجتك ارتفعت {$delta} نقطة منذ آخر مرة — تعبك واضح فيها",
                $delta < 0 => 'درجتك تراجعت '.abs($delta).' نقطة منذ آخر مرة — تستحق وقفة',
                default => __('درجتك ثابتة منذ آخر تقرير — لم يتغيّر شيء'),
            },
            'days_between' => $previous->created_at?->diffInDays($current->created_at) ?? 0,
        ];
    }
}
