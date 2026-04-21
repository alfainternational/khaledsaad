<?php

namespace App\Support\Workspaces;

use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;

class OnboardingState
{
    public const COMPLETION_KEY = 'system.onboarding_completed';

    public function isCompleted(Workspace $workspace): bool
    {
        if ($workspace->projects()->exists() || $workspace->clients()->exists()) {
            return true;
        }

        return WorkspaceData::query()
            ->where('workspace_id', $workspace->getKey())
            ->whereNull('project_id')
            ->where('key', self::COMPLETION_KEY)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function markCompleted(Workspace $workspace, array $meta = []): void
    {
        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->getKey(),
                'project_id' => null,
                'key' => self::COMPLETION_KEY,
            ],
            [
                'value_json' => [
                    'completed' => true,
                    'meta' => $meta,
                    'completed_at' => now()->toDateTimeString(),
                ],
            ],
        );
    }
}
