<?php

namespace App\Application\Admin\Accounts;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Account $account, User $actor): void
    {
        DB::transaction(function () use ($account, $actor): void {
            $meta = [
                'name' => $account->name,
                'owner_user_id' => $account->owner_user_id,
                'workspaces_count' => $account->workspaces()->count(),
                'has_subscription' => $account->subscription()->exists(),
            ];

            $accountId = $account->getKey();
            $account->forceDelete();

            $this->auditLogger->record(
                action: 'admin.account.deleted',
                targetType: 'account',
                targetId: $accountId,
                actor: $actor,
                meta: $meta,
            );
        });
    }
}
