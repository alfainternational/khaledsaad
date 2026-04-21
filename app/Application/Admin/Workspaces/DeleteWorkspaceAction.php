<?php

namespace App\Application\Admin\Workspaces;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteWorkspaceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Workspace $workspace, User $actor): void
    {
        DB::transaction(function () use ($workspace, $actor): void {
            $meta = [
                'name' => $workspace->name,
                'account_id' => $workspace->account_id,
                'members_count' => $workspace->members()->count(),
                'projects_count' => $workspace->projects()->count(),
                'clients_count' => $workspace->clients()->count(),
            ];

            $workspaceId = $workspace->getKey();
            $workspace->forceDelete();

            $this->auditLogger->record(
                action: 'admin.workspace.deleted',
                targetType: 'workspace',
                targetId: $workspaceId,
                actor: $actor,
                meta: $meta,
            );
        });
    }
}
