<?php

namespace App\Application\Admin\Accounts;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{owner_user_id:int,name:string,billing_email:string,status:string,plan_id?:int|null,subscription_status?:string|null,current_period_end?:mixed}  $data
     */
    public function handle(array $data, User $actor, ?Account $account = null): Account
    {
        return DB::transaction(function () use ($data, $actor, $account): Account {
            $isNew = $account === null;
            $account ??= new Account;

            $account->fill([
                'owner_user_id' => $data['owner_user_id'],
                'name' => $data['name'],
                'billing_email' => $data['billing_email'],
                'status' => $data['status'],
            ]);
            $account->save();

            $account->members()->where('role', 'owner')->where('user_id', '!=', $data['owner_user_id'])->update([
                'role' => 'admin',
            ]);

            $account->members()->updateOrCreate(
                ['user_id' => $data['owner_user_id']],
                ['role' => 'owner', 'status' => 'active', 'invited_at' => now()],
            );

            $planId = $data['plan_id'] ?? null;

            if ($planId !== null) {
                $account->subscription()->updateOrCreate(
                    ['account_id' => $account->getKey()],
                    [
                        'plan_id' => $planId,
                        'status' => $data['subscription_status'] ?? $account->subscription?->status ?? 'active',
                        'current_period_end' => $data['current_period_end'] ?? null,
                    ],
                );
            } elseif ($account->subscription()->exists()) {
                $account->subscription()->delete();
            }

            $this->auditLogger->record(
                action: $isNew ? 'admin.account.created' : 'admin.account.updated',
                targetType: 'account',
                targetId: $account->getKey(),
                actor: $actor,
                meta: [
                    'name' => $account->name,
                    'owner_user_id' => $account->owner_user_id,
                    'status' => $account->status,
                    'plan_id' => $planId,
                ],
            );

            return $account->refresh()->load('owner', 'subscription.plan');
        });
    }
}
