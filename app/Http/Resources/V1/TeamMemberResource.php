<?php

namespace App\Http\Resources\V1;

use App\Domain\Workspace\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkspaceMember
 */
class TeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'status' => $this->status,
            'user' => $this->whenLoaded('user', fn () => [
                'public_id' => $this->user?->public_id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'invited_at' => optional($this->invited_at)->toIso8601String(),
        ];
    }
}
