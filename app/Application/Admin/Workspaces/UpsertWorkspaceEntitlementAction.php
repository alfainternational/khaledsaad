<?php

namespace App\Application\Admin\Workspaces;

use App\Application\Admin\Support\NormalizesEntitlementValue;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class UpsertWorkspaceEntitlementAction
{
    use NormalizesEntitlementValue;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Workspace $workspace, array $data, User $actor): Entitlement
    {
        $entitlement = Entitlement::query()->updateOrCreate(
            [
                'scope_type' => 'workspace',
                'scope_id' => $workspace->getKey(),
                'key' => $data['key'],
            ],
            [
                'value_type' => $data['value_type'],
                'value' => $this->normalizeValue($data['value_type'], $data['value'] ?? null),
                'source' => 'admin_override',
            ]
        );

        $this->auditLogger->record(
            action: 'admin.workspace-entitlement.upserted',
            targetType: 'workspace',
            targetId: $workspace->getKey(),
            actor: $actor,
            workspace: $workspace,
            meta: ['key' => $entitlement->key]
        );

        return $entitlement;
    }
}
