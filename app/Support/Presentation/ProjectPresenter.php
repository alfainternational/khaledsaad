<?php

namespace App\Support\Presentation;

use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Modules\Diagnosis\MaturityAggregator;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use App\Modules\Shared\Sectors\Sector;

class ProjectPresenter
{
    public function __construct(
        private readonly ReportPresenter $reports,
        private readonly MaturityAggregator $maturity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Project $project): array
    {
        return [
            'slug' => $project->slug,
            'name' => $project->name,
            'industry' => $project->industry,
            'sector' => $project->sector,
            'sector_label' => Sector::label($project->sector),
            // الوصف الجاهز للعرض: القالب لا يركّب نصًّا من حقلين بنفسه.
            'sector_display' => Sector::describe($project->sector, $project->industry),
            'stage' => $project->stage,
            'latest_score' => $project->latest_score,
            'score_band' => $project->latest_score !== null
                ? Report::bandFor($project->latest_score)
                : null,
            'maturity' => $this->maturity($project),
        ];
    }

    /**
     * درجة النضج كما تُعرض: الرقم مع أساسه دائمًا.
     *
     * الحساب في `Diagnosis` لا هنا (§١٤) — هذه الطبقة تفوّض وتنسّق العرض.
     *
     * تُعاد `null` حين لا يكون أي محور مقيسًا. الفرق ليس تجميليًّا: صفر يُقرأ
     * حكمًا على النشاط، وغياب الرقم يقول إننا لم نقس بعد (§٤.٣).
     *
     * @return array<string, mixed>|null
     */
    private function maturity(Project $project): ?array
    {
        $result = $this->maturity->compute($project);

        if (($result['axes_active'] ?? 0) === 0) {
            return null;
        }

        return [
            MetricKey::MATURITY_SCORE => $result[MetricKey::MATURITY_SCORE],
            'axes_active' => $result['axes_active'],
            'axes_total' => $result['axes_total'],
            'evidence_level' => $result['evidence_level'],
            'is_assumption' => $result['evidence_level'] === EvidenceLevel::Inferred->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(Project $project): array
    {
        $project->loadMissing(['profile', 'reports', 'tasks', 'kpis.entries']);

        $reports = $project->reports->sortByDesc('created_at')->values();
        $latestReport = $reports->first();
        $openTasks = $project->tasks->where('status', '!=', Task::STATUS_DONE);

        return [
            ...$this->card($project),
            'profile' => $project->profile?->only([
                'business_model', 'description', 'geography', 'website',
                'monthly_budget', 'primary_goal', 'value_proposition',
            ]) ?? [],
            'latest_report' => $latestReport ? $this->reports->card($latestReport) : null,
            'comparison' => $latestReport
                ? $this->reports->comparison($latestReport, $this->reports->previousFor($latestReport))
                : null,
            'reports' => $reports->map(fn ($report) => $this->reports->card($report))->all(),
            'tasks' => [
                'open' => $openTasks->count(),
                'overdue' => $openTasks->filter(fn (Task $task) => $task->isOverdue())->count(),
                'done' => $project->tasks->where('status', Task::STATUS_DONE)->count(),
            ],
            'kpis' => $project->kpis->map(fn ($kpi) => [
                'id' => $kpi->id,
                'name' => $kpi->name,
                'unit' => $kpi->unit,
                'baseline' => $kpi->baseline,
                'target' => $kpi->target,
                'latest' => $kpi->latestValue(),
                'attainment_percent' => $kpi->attainmentPercent(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function task(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'status_label' => $task->statusLabel(),
            'priority' => $task->priority,
            'impact' => $task->impact,
            'effort' => $task->effort,
            'timeframe' => $task->timeframe,
            'due_date' => $task->due_date?->toDateString(),
            'reminder_at' => $task->reminder_at?->toIso8601String(),
            'is_overdue' => $task->isOverdue(),
            // ما يجعل المهمة قابلة للتنفيذ: الخطوات والمثال والدليل المطوَّر.
            // مصدر واحد للويب والتطبيق فلا يعرض أحدهما ما يخفيه الآخر.
            'steps' => $task->steps ?? [],
            'worked_example' => $task->worked_example,
            'guide' => $task->guide,
            'guide_status' => $task->guide_status,
            'guide_status_label' => $task->guideStatusLabel(),
            'has_guide' => $task->hasGuide(),
        ];
    }
}
