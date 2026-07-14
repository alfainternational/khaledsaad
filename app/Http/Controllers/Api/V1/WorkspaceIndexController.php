<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Resources\V1\WorkspaceSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceIndexController
{
    /**
     * الصلاحيات التي يعتمد عليها العميل لإظهار/إخفاء الإجراءات مسبقاً
     * (بدل أن يصطدم المستخدم بخطأ 403 بعد الضغط).
     */
    private const UI_BOOLEAN_ENTITLEMENTS = [
        'outputs.can_export',
        'white_label',
        'agency_mode',
    ];

    public function index(Request $request, EntitlementResolver $entitlements): AnonymousResourceCollection
    {
        $user = $request->user();

        $rows = Workspace::query()
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id)->where('status', 'active');
            })
            ->with(['members' => function ($query) use ($user): void {
                $query->where('user_id', $user->id)->where('status', 'active');
            }])
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'type', 'status']);

        foreach ($rows as $workspace) {
            $member = $workspace->members->first();
            $workspace->setAttribute('current_role', $member?->role);

            $map = [];
            foreach (self::UI_BOOLEAN_ENTITLEMENTS as $key) {
                $map[$key] = $entitlements->boolean($key, $workspace);
            }
            $map['ai_studio.monthly_credits'] = (int) $entitlements->value('ai_studio.monthly_credits', $workspace);

            $workspace->setAttribute('ui_entitlements', $map);
        }

        return WorkspaceSummaryResource::collection($rows);
    }
}
