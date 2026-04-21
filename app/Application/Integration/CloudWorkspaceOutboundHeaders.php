<?php

namespace App\Application\Integration;

use App\Domain\Workspace\Models\Workspace;

/**
 * رؤوس HTTP اختيارية لطلبات صادرة نحو خدمة سحابية مع ربط سياق الـ workspace (عزل منطقي).
 */
final class CloudWorkspaceOutboundHeaders
{
    /**
     * @return array<string, string>
     */
    public static function forWorkspace(?Workspace $workspace): array
    {
        if ($workspace === null || $workspace->public_id === null) {
            return [];
        }

        return [
            'X-Workspace-Public-Id' => (string) $workspace->public_id,
        ];
    }
}
