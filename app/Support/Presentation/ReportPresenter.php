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
        $report->loadMissing(['sections', 'findings.recommendations.task', 'toolRun.toolVersion.tool', 'project']);

        // قائمة المنافسين تُقرأ حيّة لا من لقطة التقرير: تأكيد/استبعاد يظهر فورًا.
        $competitorView = $this->competitors->forReport($report->project);

        return [
            'id' => $report->id,
            'title' => $report->title,
            'status' => $report->status,
            'score' => $report->score,
            'score_band' => $report->score_band,
            'summary' => $report->summary,
            // توثيق أن التقرير رُوجع بشريًا: بيان صادق موجز، لا لافتة تمدح.
            // الإحساس البشري يجيء من نص التقرير نفسه (تعليمات المُراجِع)، لا
            // من جملة تدّعي أنها مكتوبة بيد.
            'is_manually_reviewed' => $report->review_mode === 'manual',
            'reviewed_at' => $report->reviewed_at?->locale('ar')->translatedFormat('j F Y'),
            'reviewer_name' => $report->review_mode === 'manual' ? config('brand.name', 'خالد سعد') : null,
            'assumptions' => $report->assumptions ?? [],
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
                'basis_label' => $finding->is_assumption ? 'افتراض' : 'مدعوم بدليل',
                'recommendations' => $finding->recommendations->map(fn ($recommendation) => [
                    'id' => $recommendation->id,
                    'title' => $recommendation->title,
                    'description' => $recommendation->description,
                    'root_cause' => $recommendation->root_cause,
                    'commercial_impact' => $recommendation->commercial_impact,
                    'action_steps' => $recommendation->action_steps ?? [],
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
        return [
            'id' => $report->id,
            'title' => $report->title,
            'score' => $report->score,
            'score_band' => $report->score_band,
            'status' => $report->status,
            'created_at' => $report->created_at?->toIso8601String(),
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

        return $this->deltaBlock($previous, $current, $delta);
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
                default => 'درجتك ثابتة منذ آخر تقرير — لم يتغيّر شيء',
            },
            'days_between' => $previous->created_at?->diffInDays($current->created_at) ?? 0,
        ];
    }
}
