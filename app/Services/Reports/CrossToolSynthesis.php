<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ToolRun;
use Illuminate\Support\Collection;

class CrossToolSynthesis
{
    /** @param Collection<int,Report> $reports @return array<string,mixed> */
    public function build(Collection $reports): array
    {
        $findings = $reports->flatMap(function (Report $report) {
            $tool = $report->toolRun->toolVersion->tool;

            return $report->findings->map(fn ($finding) => [
                'source_report_id' => $report->id,
                'source_tool_key' => $tool->key,
                'source_tool_title' => $tool->title,
                'category' => $finding->category,
                'title' => $finding->title,
                'description' => $finding->description,
                'severity' => $finding->severity,
                'confidence' => $finding->confidence,
                'claim_type' => $finding->is_assumption ? 'assumption' : 'evidence',
                'evidence' => $finding->evidence,
            ]);
        })->values();

        $groups = $findings->groupBy(fn (array $finding) => mb_strtolower(trim((string) $finding['category'])));
        $agreements = [];
        $divergences = [];

        foreach ($groups as $category => $items) {
            $sources = $items->pluck('source_tool_key')->unique()->values();
            if ($sources->count() < 2) {
                continue;
            }
            $entry = [
                'category' => $items->first()['category'] ?: $category,
                'source_tools' => $sources->all(),
                'source_report_ids' => $items->pluck('source_report_id')->unique()->values()->all(),
                'severities' => $items->pluck('severity')->filter()->unique()->values()->all(),
                'findings' => $items->pluck('title')->unique()->values()->all(),
            ];
            if (count($entry['severities']) <= 1) {
                $agreements[] = $entry;
            } else {
                $divergences[] = $entry + [
                    'resolution' => 'تُعرض القراءتان بمصدريهما وتحتاجان مراجعة السياق قبل اعتماد شدة واحدة.',
                ];
            }
        }

        return [
            'source_report_ids' => $reports->pluck('id')->values()->all(),
            'findings' => $findings->all(),
            'agreements' => $agreements,
            'divergences' => $divergences,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function priorResults(ToolRun $run): array
    {
        $currentToolKey = $run->toolVersion->tool->key;

        return $run->project->reports()
            ->where('status', 'published')
            ->with(['toolRun.toolVersion.tool', 'findings'])
            ->latest('created_at')->latest('id')->get()
            ->filter(fn (Report $report) => $report->toolRun?->toolVersion?->tool !== null
                && $report->toolRun->toolVersion->tool->key !== $currentToolKey)
            ->unique(fn (Report $report) => $report->toolRun->toolVersion->tool->key)
            ->take(10)
            ->map(fn (Report $report) => [
                'source_report_id' => $report->id,
                'source_tool_key' => $report->toolRun->toolVersion->tool->key,
                'source_tool_title' => $report->toolRun->toolVersion->tool->title,
                'score' => $report->score,
                'score_band' => $report->score_band,
                'summary' => $report->summary,
                'findings' => $report->findings->take(8)->map(fn ($finding) => [
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'severity' => $finding->severity,
                    'confidence' => $finding->confidence,
                    'claim_type' => $finding->is_assumption ? 'assumption' : 'evidence',
                ])->values()->all(),
            ])->values()->all();
    }
}
