<?php

namespace App\Application\Admin\Workspaces;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class DeleteWorkspaceEntitlementAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Workspace $workspace, Entitlement $entitlement, User $actor): void
    {
        $key = $entitlement->key;
        $entitlement->delete();

        $this->auditLogger->record(
            action: 'admin.workspace-entitlement.deleted',
            targetType: 'workspace',
            targetId: $workspace->getKey(),
            actor: $actor,
            workspace: $workspace,
            meta: ['key' => $key]
        );
    }
}
