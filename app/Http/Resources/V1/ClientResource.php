<?php

namespace App\Http\Resources\V1;

use App\Domain\Client\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'status' => $this->whenHas('status'),
            'contact_info' => $this->whenHas('contact_info'),
            'projects_count' => $this->whenCounted('projects'),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
