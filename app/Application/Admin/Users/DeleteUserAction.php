<?php

namespace App\Application\Admin\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Enums\UserStatus;
use App\Models\User;

class DeleteUserAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $target, User $actor): void
    {
        abort_if($target->is($actor), 422, 'لا يمكن حذف حسابك الحالي من لوحة الإدارة.');

        if ($target->is_super_admin) {
            $remainingActiveSuperAdmins = User::query()
                ->where('is_super_admin', true)
                ->where('status', UserStatus::Active)
                ->whereKeyNot($target->getKey())
                ->count();

            abort_if($remainingActiveSuperAdmins === 0, 422, 'لا يمكن حذف آخر مدير عام نشط.');
        }

        $meta = [
            'email' => $target->email,
            'owned_accounts_count' => $target->ownedAccounts()->count(),
            'account_memberships_count' => $target->accountMemberships()->count(),
            'workspace_memberships_count' => $target->workspaceMemberships()->count(),
        ];

        $targetId = $target->getKey();
        $target->forceDelete();

        $this->auditLogger->record(
            action: 'admin.user.deleted',
            targetType: 'user',
            targetId: $targetId,
            actor: $actor,
            meta: $meta,
        );
    }
}
