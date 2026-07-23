<?php

namespace App\Support\Presentation;

use App\Models\Project;
use App\Models\Report;
use App\Models\Task;

class ProjectPresenter
{
    public function __construct(private readonly ReportPresenter $reports) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Project $project): array
    {
        return [
            'slug' => $project->slug,
            'name' => $project->name,
            'industry' => $project->industry,
            'stage' => $project->stage,
            'latest_score' => $project->latest_score,
            'score_band' => $project->latest_score !== null
                ? Report::bandFor($project->latest_score)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(Project $project): array
    {
        $project->loadMissing(['profile', 'reports', 'tasks', 'kpis.entries']);

        $reports = $project->reports->sortByDesc('created_at')->values();
        $openTasks = $project->tasks->where('status', '!=', Task::STATUS_DONE);

        return [
            ...$this->card($project),
            'profile' => $project->profile?->only([
                'business_model', 'description', 'geography', 'website',
                'monthly_budget', 'primary_goal', 'value_proposition',
            ]) ?? [],
            'latest_report' => $reports->first() ? $this->reports->card($reports->first()) : null,
            'comparison' => $this->reports->comparison(
                $reports->first() ?? new Report,
                $reports->get(1),
            ),
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
            'due_date' => $task->due_date?->toDateString(),
            'is_overdue' => $task->isOverdue(),
        ];
    }
}
