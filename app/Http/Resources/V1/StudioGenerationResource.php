<?php

namespace App\Http\Resources\V1;

use App\Domain\AI\Models\AIGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AIGeneration
 */
class StudioGenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'status' => $this->status,
            'template_id' => $this->template_id,
            'template' => $this->whenLoaded('template', fn () => [
                'code' => $this->template?->code,
                'name' => $this->template?->name,
            ]),
            'project_id' => $this->project_id,
            'output' => $this->output,
            'tokens_used' => $this->tokens_used,
            'error' => $this->error,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
