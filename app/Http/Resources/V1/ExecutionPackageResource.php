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
            'deadline' => optional($this->deadline)->toDateString(),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task) => [
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
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
}
