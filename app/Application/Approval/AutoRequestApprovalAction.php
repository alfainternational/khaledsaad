<?php

namespace App\Application\Approval;

use App\Domain\Approval\Models\Approval;
use Illuminate\Support\Facades\Log;

/**
 * إنشاء طلب موافقة تلقائياً عند اكتمال مخرج قابل للمراجعة (توليد AI أو حزمة تنفيذ)،
 * حتى لا يجد المستخدم شاشة الموافقات فارغة دائماً. best-effort: لا يكسر التدفق الأصلي.
 */
class AutoRequestApprovalAction
{
    public function forItem(
        int $workspaceId,
        ?int $projectId,
        string $itemType,
        int $itemId,
        ?string $note = null,
    ): ?Approval {
        // approvals.project_id إلزامي في المخطط — العناصر غير المرتبطة بمشروع لا تدخل الطابور.
        if ($projectId === null) {
            return null;
        }

        try {
            return Approval::query()->updateOrCreate([
                'workspace_id' => $workspaceId,
                'project_id' => $projectId,
                'item_type' => $itemType,
                'item_id' => $itemId,
            ], [
                'status' => 'pending',
                'note' => $note,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Auto approval request failed: '.$e->getMessage(), [
                'item_type' => $itemType,
                'item_id' => $itemId,
            ]);

            return null;
        }
    }
}
