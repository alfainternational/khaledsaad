<?php

namespace App\Http\Resources\V1;

use App\Domain\Tool\Models\Tool;
use App\Support\Tooling\ToolDisplayCatalog;
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
            'name' => ToolDisplayCatalog::label($this->code, $this->name ?: $this->code),
            'description' => ToolDisplayCatalog::shortDescription($this->code, $this->description ?: ''),
            'module' => $this->module,
            'stage' => $this->stage,
            'sort_order' => $this->sort_order,
            'output_type' => $this->output_type,
            'estimated_minutes' => $this->estimated_minutes,
            'has_guided_mode' => (bool) $this->has_guided_mode,
            'has_structured_mode' => (bool) $this->has_structured_mode,
            'has_expert_mode' => (bool) $this->has_expert_mode,
            'depends_on' => $this->depends_on_json ?? [],
            'feeds_into' => $this->feeds_into_json ?? [],
            'status' => $this->status,
            'unlocked' => $this->when(isset($this->unlocked), (bool) ($this->unlocked ?? false)),
            'completed_in_current_project' => $this->when(isset($this->completed_in_current_project), (bool) ($this->completed_in_current_project ?? false)),
            'current_project_runs' => $this->when(isset($this->current_project_runs), (int) ($this->current_project_runs ?? 0)),
            'recommended_now' => $this->when(isset($this->recommended_now), (bool) ($this->recommended_now ?? false)),
        ];
    }
}
