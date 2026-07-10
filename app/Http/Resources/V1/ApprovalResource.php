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
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'public_id' => $this->reviewer?->public_id,
                'name' => $this->reviewer?->name,
            ]),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
