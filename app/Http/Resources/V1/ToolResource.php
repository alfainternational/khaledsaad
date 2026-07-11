<?php

namespace App\Http\Resources\V1;

use App\Domain\Tool\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tool
 */
class ToolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->module,
            'stage' => $this->stage,
            'output_type' => $this->output_type,
            'estimated_minutes' => $this->estimated_minutes,
            'has_guided_mode' => (bool) $this->has_guided_mode,
            'has_structured_mode' => (bool) $this->has_structured_mode,
            'has_expert_mode' => (bool) $this->has_expert_mode,
            'depends_on' => $this->depends_on_json ?? [],
            'feeds_into' => $this->feeds_into_json ?? [],
            'status' => $this->status,
        ];
    }
}
