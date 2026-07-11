<?php

namespace App\Http\Resources\V1;

use App\Domain\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * النسخة القياسية لمشروع في القوائم.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'stage' => $this->stage,
            'status' => $this->status,
            'sector' => $this->sector,
            'market_country' => $this->market_country,
            'primary_domain' => $this->primary_domain,
            'monitoring_enabled' => (bool) $this->monitoring_enabled,
            'client' => new ClientResource($this->whenLoaded('client')),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
