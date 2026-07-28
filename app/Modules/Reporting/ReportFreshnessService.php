<?php

namespace App\Modules\Reporting;

use App\Models\AgencyReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportFreshnessService
{
    /** @return array{is_stale:bool,state:string,label:string,reasons:array<int,string>,source_snapshot_at:?string,latest_source_at:?string} */
    public function status(AgencyReport $report): array
    {
        $report->loadMissing('project');
        $projectId = $report->project_id;
        $generatedAt = $report->generated_at ?? $report->created_at;
        $categories = [
            'knowledge' => $this->latest([
                $this->max('projects', 'updated_at', ['id' => $projectId]),
                $this->max('project_profiles', 'updated_at', ['project_id' => $projectId]),
                $this->max('project_answers', 'updated_at', ['project_id' => $projectId]),
                $this->max('project_knowledge_sources', 'recorded_at', ['project_id' => $projectId]),
            ]),
            'diagnostics' => $this->latestDiagnosticChange($projectId),
            'consultation' => $this->latestConsultationChange($projectId),
            'operations' => $this->latest([
                $this->max('project_competitors', 'updated_at', ['project_id' => $projectId]),
                $this->max('project_audiences', 'updated_at', ['project_id' => $projectId]),
                $this->max('kpis', 'updated_at', ['project_id' => $projectId]),
                $this->max('tasks', 'updated_at', ['project_id' => $projectId]),
            ]),
        ];

        $labels = [
            'knowledge' => 'تغيرت معلومات المشروع بعد إنشاء هذا الإصدار.',
            'diagnostics' => 'ظهرت نتيجة تشخيصية أحدث بعد إنشاء هذا الإصدار.',
            'consultation' => 'تغيرت إجابات أو أدلة أو قرارات الاستشارة بعد إنشاء هذا الإصدار.',
            'operations' => 'تغيرت بيانات التنفيذ أو المؤشرات بعد إنشاء هذا الإصدار.',
        ];
        $reasons = [];
        foreach ($categories as $category => $timestamp) {
            if ($timestamp !== null && $generatedAt !== null && $timestamp->gt($generatedAt)) {
                $reasons[] = $labels[$category];
            }
        }
        $latest = $this->latest(array_values($categories));
        $stale = $reasons !== [];

        return [
            'is_stale' => $stale,
            'state' => $stale ? 'stale' : 'fresh',
            'label' => $stale ? 'يحتاج تحديثًا' : 'محدّث',
            'reasons' => $reasons,
            'source_snapshot_at' => $generatedAt?->toIso8601String(),
            'latest_source_at' => $latest?->toIso8601String(),
        ];
    }

    private function latestDiagnosticChange(int $projectId): ?Carbon
    {
        $reportIds = DB::table('reports')->where('project_id', $projectId)->pluck('id');

        return $this->latest([
            $this->max('reports', 'created_at', ['project_id' => $projectId]),
            $reportIds->isEmpty() ? null : $this->asCarbon(DB::table('findings')->whereIn('report_id', $reportIds)->max('updated_at')),
            $reportIds->isEmpty() ? null : $this->asCarbon(DB::table('recommendations')->whereIn('report_id', $reportIds)->max('updated_at')),
        ]);
    }

    private function latestConsultationChange(int $projectId): ?Carbon
    {
        $sessionIds = DB::table('consultation_sessions')->where('project_id', $projectId)->pluck('id');
        if ($sessionIds->isEmpty()) {
            return null;
        }

        // حالة الجلسة نفسها مؤشر تشغيل (queued/completed)، وليست مادة في التقرير.
        // احتساب updated_at للجلسة كان يجعل التقرير قديمًا لحظة تحويلها إلى completed
        // بعد إنشائه، بينما التغييرات الفعلية محفوظة في الجداول أدناه.
        return $this->latest(collect([
            'consultation_answers', 'consultation_evidence',
            'consultation_inferences', 'consultation_conflicts', 'consultation_module_states',
        ])->map(function (string $table) use ($sessionIds): ?Carbon {
            return $this->asCarbon(DB::table($table)
                ->whereIn('consultation_session_id', $sessionIds)
                ->max('updated_at'));
        })->all());
    }

    /** @param array<string,int|string> $where */
    private function max(string $table, string $column, array $where): ?Carbon
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $query = DB::table($table);
        foreach ($where as $key => $value) {
            $query->where($key, $value);
        }

        return $this->asCarbon($query->max($column));
    }

    /** @param array<int,Carbon|null> $timestamps */
    private function latest(array $timestamps): ?Carbon
    {
        return collect($timestamps)->filter()->sortByDesc(fn (Carbon $time) => $time->getTimestamp())->first();
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value);
    }
}
