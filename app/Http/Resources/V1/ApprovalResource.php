<?php

namespace App\Http\Resources\V1;

use App\Domain\Approval\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Approval
 */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'status' => $this->status,
            'note' => $this->note,
            'project' => $this->whenLoaded('project', fn () => [
                'public_id' => $this->project?->public_id,
                'name' => $this->project?->name,
                'client' => $this->project?->relationLoaded('client') ? [
                    'public_id' => $this->project?->client?->public_id,
                    'name' => $this->project?->client?->name,
                ] : null,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'public_id' => $this->reviewer?->public_id,
                'name' => $this->reviewer?->name,
            ]),
            'item' => $this->approvalItem(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalItem(): array
    {
        if ($this->item_type === 'tool_run') {
            $toolRun = $this->relationLoaded('toolRun') ? $this->toolRun : null;

            return [
                'kind' => 'tool_run',
                'kind_label' => 'تشغيل أداة',
                'public_id' => $toolRun?->public_id,
                'title' => data_get($toolRun?->summary_json, 'headline')
                    ?? $toolRun?->tool?->name
                    ?? 'تشغيل أداة يحتاج مراجعة',
                'tool_code' => $toolRun?->tool_code,
                'tool_name' => $toolRun?->tool?->name,
            ];
        }

        if ($this->item_type === 'ai_generation') {
            $generation = $this->relationLoaded('aiGeneration') ? $this->aiGeneration : null;

            return [
                'kind' => 'ai_generation',
                'kind_label' => 'مخرج استوديو',
                'public_id' => $generation?->public_id,
                'title' => $generation?->template?->name ?? 'مخرج استوديو يحتاج مراجعة',
                'template_code' => $generation?->template?->code,
                'template_name' => $generation?->template?->name,
            ];
        }

        return [
            'kind' => $this->item_type,
            'kind_label' => $this->item_type,
            'public_id' => null,
            'title' => 'عنصر يحتاج مراجعة',
        ];
    }
}
