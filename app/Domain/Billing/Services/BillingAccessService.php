<?php

namespace App\Domain\Billing\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

/**
 * صلاحية إدارة الفوترة وفق مصفوفة الوصول:
 * مالك الحساب، أو عضو حساب بدور فوترة، أو مالك مساحة العمل التابعة للحساب.
 */
class BillingAccessService
{
    /** @var list<string> أدوار عضوية الحساب المخوّلة بالفوترة */
    private const ACCOUNT_BILLING_ROLES = ['owner', 'billing', 'billing_admin'];

    public function canManage(User $user, Account $account, ?Workspace $workspace = null): bool
    {
        if ($user->id === $account->owner_user_id) {
            return true;
        }

        $hasBillingMembership = $account->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('role', self::ACCOUNT_BILLING_ROLES)
            ->exists();

        if ($hasBillingMembership) {
            return true;
        }

        if ($workspace !== null && (int) $workspace->account_id === (int) $account->id) {
            return $workspace->members()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('role', WorkspaceRole::Owner->value)
                ->exists();
        }

        return false;
    }
}
