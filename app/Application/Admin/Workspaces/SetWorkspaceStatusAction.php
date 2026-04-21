<?php

namespace App\Application\Admin\Workspaces;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class SetWorkspaceStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Workspace $workspace, string $status, User $actor): Workspace
    {
        $previousStatus = $workspace->status;

        $workspace->forceFill([
            'status' => $status,
        ])->save();

        $this->auditLogger->record(
            action: 'admin.workspace.status.updated',
            targetType: 'workspace',
            targetId: $workspace->getKey(),
            actor: $actor,
            workspace: $workspace,
            meta: [
                'workspace_name' => $workspace->name,
                'previous_status' => $previousStatus,
                'new_status' => $status,
            ],
        );

        return $workspace->refresh();
    }
}
