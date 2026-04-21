<?php

namespace App\Application\Admin\Accounts;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;

class SetAccountStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Account $account, string $status, User $actor): Account
    {
        $previousStatus = $account->status;

        $account->forceFill([
            'status' => $status,
        ])->save();

        $this->auditLogger->record(
            action: 'admin.account.status.updated',
            targetType: 'account',
            targetId: $account->getKey(),
            actor: $actor,
            meta: [
                'account_name' => $account->name,
                'previous_status' => $previousStatus,
                'new_status' => $status,
            ],
        );

        return $account->refresh();
    }
}
