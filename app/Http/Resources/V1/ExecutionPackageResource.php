<?php

namespace App\Http\Resources\V1;

use App\Domain\Execution\Models\ExecutionPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExecutionPackage
 */
class ExecutionPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'title' => $this->title,
            'problem' => $this->problem,
            'evidence' => $this->evidence,
            'decision' => $this->decision,
            'measurement_plan' => $this->measurement_plan,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'progress' => $this->progress(),
            'measurement_summary' => $this->whenLoaded('reports', fn () => $this->measurementSummary()),
            'available_actions' => $this->availableActions(),
            'deadline' => optional($this->deadline)->toDateString(),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task) => [
                'public_id' => $task->public_id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'status_label' => $this->taskStatusLabel((string) $task->status),
                'available_actions' => $this->taskAvailableActions((string) $task->status),
                'assigned_to' => $task->assigned_to,
                'assignee' => $task->relationLoaded('assignee') && $task->assignee ? [
                    'public_id' => $task->assignee->public_id,
                    'name' => $task->assignee->name,
                    'email' => $task->assignee->email,
                ] : null,
                'due_date' => optional($task->due_date)->toDateString(),
                'order_index' => $task->order_index,
            ])->values()),
            'assets' => $this->whenLoaded('assets', fn () => $this->assets->map(fn ($asset) => [
                'public_id' => $asset->public_id,
                'type' => $asset->type,
                'type_label' => $this->assetTypeLabel((string) $asset->type),
                'title' => $asset->title,
                'body' => $asset->body,
                'meta' => $asset->meta_json ?? [],
            ])->values()),
            'reports' => $this->whenLoaded('reports', fn () => $this->reports->map(fn ($report) => [
                'public_id' => $report->public_id,
                'phase' => $report->phase,
                'phase_label' => $this->reportPhaseLabel((string) $report->phase),
                'progress' => $report->progress,
                'notes' => $report->notes_json ?? [],
                'metrics' => $report->metrics_json ?? [],
                'created_at' => optional($report->created_at)->toIso8601String(),
            ])->values()),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'proposed' => 'مقترحة',
            'in_review' => 'قيد المراجعة',
            'approved' => 'معتمدة',
            'in_progress' => 'قيد التنفيذ',
            'executed' => 'منفّذة',
            'measuring' => 'تحت القياس',
            default => (string) $this->status,
        };
    }

    /**
     * @return array{total_tasks: int, done_tasks: int, percent: int}
     */
    private function progress(): array
    {
        if (! $this->relationLoaded('tasks')) {
            return ['total_tasks' => 0, 'done_tasks' => 0, 'percent' => 0];
        }

        $total = $this->tasks->count();
        $done = $this->tasks->where('status', 'done')->count();

        return [
            'total_tasks' => $total,
            'done_tasks' => $done,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /**
     * @return array{reports_count: int, latest_phase: ?string, latest_phase_label: ?string, latest_progress: ?int, latest_metric: ?array<string, mixed>, latest_note: ?string}
     */
    private function measurementSummary(): array
    {
        $latest = $this->reports->first();
        $metric = $latest ? collect($latest->metrics_json ?? [])->first() : null;

        return [
            'reports_count' => $this->reports->count(),
            'latest_phase' => $latest?->phase,
            'latest_phase_label' => $latest ? $this->reportPhaseLabel((string) $latest->phase) : null,
            'latest_progress' => $latest?->progress,
            'latest_metric' => $metric ?: null,
            'latest_note' => $latest->notes_json['summary'] ?? null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function availableActions(): array
    {
        return match ($this->status) {
            'proposed' => ['request_approval'],
            'approved' => ['start_execution'],
            'in_progress' => $this->allTasksDone() ? ['mark_executed'] : [],
            'executed' => ['start_measuring'],
            default => [],
        };
    }

    private function allTasksDone(): bool
    {
        if ($this->relationLoaded('tasks')) {
            return $this->tasks->isNotEmpty() && $this->tasks->every(fn ($task): bool => $task->status === 'done');
        }

        return $this->tasks()->exists() && ! $this->tasks()->where('status', '!=', 'done')->exists();
    }

    private function taskStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'لم تبدأ',
            'in_progress' => 'قيد التنفيذ',
            'done' => 'منجزة',
            default => $status,
        };
    }

    /**
     * @return array<int, string>
     */
    private function taskAvailableActions(string $status): array
    {
        if ($this->status !== 'in_progress') {
            return [];
        }

        return match ($status) {
            'pending' => ['start', 'complete'],
            'in_progress' => ['complete', 'reopen'],
            'done' => ['reopen'],
            default => [],
        };
    }

    private function assetTypeLabel(string $type): string
    {
        return match ($type) {
            'copy' => 'نص تسويقي',
            'design_brief' => 'موجز تصميم',
            'dev_brief' => 'موجز تطوير',
            'ad' => 'إعلان',
            'measurement' => 'قياس',
            'other' => 'أصل آخر',
            default => $type,
        };
    }

    private function reportPhaseLabel(string $phase): string
    {
        return match ($phase) {
            'discovery' => 'اكتشاف',
            'planning' => 'تخطيط',
            'execution' => 'تنفيذ',
            'validation' => 'تحقق',
            default => $phase,
        };
    }
}
