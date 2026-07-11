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
            'available_actions' => $this->availableActions(),
            'deadline' => optional($this->deadline)->toDateString(),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task) => [
                'public_id' => $task->public_id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'status_label' => $this->taskStatusLabel((string) $task->status),
                'assigned_to' => $task->assigned_to,
                'due_date' => optional($task->due_date)->toDateString(),
                'order_index' => $task->order_index,
            ])->values()),
            'assets' => $this->whenLoaded('assets', fn () => $this->assets->map(fn ($asset) => [
                'type' => $asset->type,
                'title' => $asset->title,
                'body' => $asset->body,
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
     * @return array<int, string>
     */
    private function availableActions(): array
    {
        return match ($this->status) {
            'proposed' => ['approve'],
            'approved' => ['start_execution'],
            'in_progress' => ['mark_executed'],
            'executed' => ['start_measuring'],
            default => [],
        };
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
}
