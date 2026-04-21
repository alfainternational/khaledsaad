<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?User $actor = null,
        ?Workspace $workspace = null,
        array $meta = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'workspace_id' => $workspace?->getKey(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
