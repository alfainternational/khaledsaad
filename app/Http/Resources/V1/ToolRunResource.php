<?php

namespace App\Http\Resources\V1;

use App\Domain\Tool\Models\ToolRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ToolRun
 */
class ToolRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'tool_code' => $this->tool_code,
            'mode' => $this->mode,
            'completeness_score' => $this->completeness_score,
            'summary' => $this->summary_json,
            'output' => $this->output_json,
            'inputs' => $this->inputs_json,
            'next_actions' => $this->next_actions_json ?? [],
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
