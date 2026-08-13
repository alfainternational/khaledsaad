<?php

namespace App\Modules\Reporting\Publication;

use App\Models\Report;

class ReportContractAssembler
{
    /** @return array<string, mixed> */
    public function assemble(Report $report): array
    {
        $report->loadMissing(['findings.evidenceAnswer', 'findings.recommendations.objective', 'findings.recommendations.metricObjective', 'findings.recommendations.template', 'scoringItems', 'humanTraces']);

        return [
            'schema_version' => 2,
            'provenance' => $report->provenance ?: 'automated',
            'human_traces' => $report->humanTraces->map->only(['type', 'body', 'created_by'])->values()->all(),
            'score' => [
                'value' => (float) $report->score,
                'raw' => (float) ($report->score_raw ?? $report->scoringItems->sum('points')),
                'max' => (float) ($report->score_max ?? $report->scoringItems->sum('weight')),
            ],
            'scoring' => $report->scoringItems->map(fn ($item) => [
                'item_key' => $item->item_key, 'weight' => $item->weight,
                'coefficient' => $item->coefficient, 'points' => $item->points,
            ])->values()->all(),
            'known_placeholders' => ['اسم العميل', 'اسم المشروع', 'السعر', 'مدينتك', 'مصدر التعارف'],
            'findings' => $report->findings->values()->map(fn ($finding) => [
                'id' => $finding->id,
                'title' => $finding->title,
                'description' => $finding->description,
                'severity' => $finding->severity,
                'is_assumption' => (bool) $finding->is_assumption,
                'evidence' => [
                    'answer_ref' => $finding->evidence_answer_id ? 'answer:'.$finding->evidence_answer_id : '',
                    'quote' => $finding->evidence_quote ?: ($finding->evidence ?? ''),
                ],
                'recommendation' => ($recommendation = $finding->recommendations->first()) ? [
                    'objective_id' => $recommendation->objective?->slug ?? '',
                    'title' => $recommendation->title,
                    'rationale' => $recommendation->description,
                    'impact' => $recommendation->impact,
                    'effort' => $recommendation->effort,
                    'duration_days' => $recommendation->duration_days,
                    'metric' => [
                        'label' => $recommendation->kpi_hint,
                        'objective_id' => $recommendation->metricObjective?->slug ?? '',
                    ],
                    'steps' => $recommendation->action_steps ?? [],
                    'deliverable' => $recommendation->deliverable,
                    'done_when' => $recommendation->done_when,
                    'first_five_minutes' => $recommendation->first_five_minutes,
                    'expected_failure' => $recommendation->expected_failure,
                    'template' => $recommendation->template_payload,
                    'degraded' => (bool) $recommendation->degraded,
                    'degrade_reason' => $recommendation->degrade_reason,
                ] : null,
            ])->values()->all(),
        ];
    }
}
