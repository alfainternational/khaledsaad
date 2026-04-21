<?php

namespace App\Support\Workspaces;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;

class WorkspaceJourneyStore
{
    public const PROFILE_KEY = 'journey.profile';

    public const SNAPSHOT_KEY = 'journey.snapshot';

    public const READINESS_KEY = 'readiness.snapshot';

    /**
     * @return array<string, mixed>
     */
    public function getProfile(Workspace $workspace, ?Project $project = null): array
    {
        return $this->getValue($workspace, $project, self::PROFILE_KEY);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function putProfile(Workspace $workspace, array $payload, ?Project $project = null): WorkspaceData
    {
        return $this->putValue($workspace, $project, self::PROFILE_KEY, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(Workspace $workspace, ?Project $project = null): array
    {
        return $this->getValue($workspace, $project, self::SNAPSHOT_KEY);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function putSnapshot(Workspace $workspace, array $payload, ?Project $project = null): WorkspaceData
    {
        return $this->putValue($workspace, $project, self::SNAPSHOT_KEY, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReadiness(Workspace $workspace, ?Project $project = null): array
    {
        return $this->getValue($workspace, $project, self::READINESS_KEY);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function putReadiness(Workspace $workspace, array $payload, ?Project $project = null): WorkspaceData
    {
        return $this->putValue($workspace, $project, self::READINESS_KEY, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function getValue(Workspace $workspace, ?Project $project, string $key): array
    {
        return WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project?->id)
            ->where('key', $key)
            ->first()?->value_json ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function putValue(Workspace $workspace, ?Project $project, string $key, array $payload): WorkspaceData
    {
        return WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project?->id,
                'key' => $key,
            ],
            [
                'value_json' => $payload,
            ],
        );
    }
}
