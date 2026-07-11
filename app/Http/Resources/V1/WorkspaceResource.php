<?php

namespace App\Http\Resources\V1;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * النسخة الكاملة لمساحة عمل واحدة (تشمل الدور والصلاحيات عند توفّرها).
 *
 * @mixin Workspace
 */
class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'role' => $this->whenHas('current_role'),
            // خريطة الصلاحيات المفعّلة {key: value} — تُحقن من الـ controller عبر EntitlementResolver.
            'entitlements' => $this->whenHas('resolved_entitlements'),
            'branding' => $this->when(! empty($this->branding_json), $this->branding_json),
        ];
    }
}
