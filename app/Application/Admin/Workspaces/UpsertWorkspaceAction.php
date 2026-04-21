<?php

namespace App\Application\Admin\Workspaces;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertWorkspaceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{account_id:int,owner_user_id?:int|null,name:string,type:string,status:string}  $data
     */
    public function handle(array $data, User $actor, ?Workspace $workspace = null): Workspace
    {
        return DB::transaction(function () use ($data, $actor, $workspace): Workspace {
            $isNew = $workspace === null;
            $workspace ??= new Workspace;

            $workspace->fill([
                'account_id' => $data['account_id'],
                'name' => $data['name'],
                'type' => $data['type'],
                'status' => $data['status'],
            ]);
            $workspace->save();

            $account = Account::query()->findOrFail($data['account_id']);
            $ownerUserId = $data['owner_user_id'] ?? $account->owner_user_id;

            $workspace->members()->where('role', 'owner')->where('user_id', '!=', $ownerUserId)->update([
                'role' => 'admin',
            ]);

            $workspace->members()->updateOrCreate(
                ['user_id' => $ownerUserId],
                ['role' => 'owner', 'status' => 'active', 'invited_at' => now()],
            );

            $account->members()->updateOrCreate(
                ['user_id' => $ownerUserId],
                [
                    'role' => $ownerUserId === $account->owner_user_id ? 'owner' : 'admin',
                    'status' => 'active',
                    'invited_at' => now(),
                ],
            );

            $this->auditLogger->record(
                action: $isNew ? 'admin.workspace.created' : 'admin.workspace.updated',
                targetType: 'workspace',
                targetId: $workspace->getKey(),
                actor: $actor,
                workspace: $workspace,
                meta: [
                    'name' => $workspace->name,
                    'account_id' => $workspace->account_id,
                    'type' => $workspace->type,
                    'status' => $workspace->status,
                    'owner_user_id' => $ownerUserId,
                ],
            );

            return $workspace->refresh()->load('account.owner');
        });
    }
}
