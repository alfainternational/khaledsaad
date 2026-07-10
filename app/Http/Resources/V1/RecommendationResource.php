<?php

namespace App\Http\Resources\V1;

use App\Domain\Execution\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recommendation
 */
class RecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'area' => $this->area,
            'title' => $this->title,
            'priority' => $this->priority,
            'severity' => $this->severity,
            'evidence' => $this->evidence,
            'rationale' => $this->rationale,
            'estimated_impact' => $this->estimated_impact,
            'confidence' => $this->confidence,
            'status' => $this->status,
            'execution_packages' => ExecutionPackageResource::collection(
                $this->whenLoaded('executionPackages')
            ),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
