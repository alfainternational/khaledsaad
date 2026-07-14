<?php

namespace App\Http\Resources\V1;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * نسخة خفيفة لقوائم مساحات العمل (المبدّل، الفهرس).
 *
 * @mixin Workspace
 */
class WorkspaceSummaryResource extends JsonResource
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
            // الدور الحالي للمستخدم في هذه المساحة (يُحقن من الـ controller عند توفّره).
            'role' => $this->whenHas('current_role'),
            // خريطة صلاحيات مختصرة للعميل ليُخفي/يُعطّل الإجراءات المقفلة مسبقاً.
            'entitlements' => $this->whenHas('ui_entitlements'),
        ];
    }
}
