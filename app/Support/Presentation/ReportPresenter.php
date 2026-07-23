<?php

namespace App\Support\Presentation;

use App\Models\Report;
use App\Services\Competitors\CompetitorRegistry;
use App\Services\Reports\ReportCharts;

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
                    'impact' => $recommendation->impact,
                    'impact_label' => $recommendation->impactLabel(),
                    'effort' => $recommendation->effort,
                    'effort_label' => $recommendation->effortLabel(),
                    'priority' => $recommendation->priority,
                    'kpi_hint' => $recommendation->kpi_hint,
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

        return [
            'previous_score' => $previous->score,
            'current_score' => $current->score,
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'label' => match (true) {
                $delta > 0 => "تحسّن بمقدار {$delta} نقطة منذ التقرير السابق",
                $delta < 0 => 'تراجع بمقدار '.abs($delta).' نقطة منذ التقرير السابق',
                default => 'الدرجة لم تتغير منذ التقرير السابق',
            },
            'days_between' => $previous->created_at?->diffInDays($current->created_at) ?? 0,
        ];
    }
}
