<?php

namespace App\Support\Workspaces;

use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;

class WorkspaceProfileStore
{
    public const PROFILE_KEY = 'business.profile';

    /**
     * @return array<string, mixed>
     */
    public function get(Workspace $workspace): array
    {
        $profile = WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('project_id')
            ->where('key', self::PROFILE_KEY)
            ->first();

        $data = $profile?->value_json ?? [];

        if (! isset($data['persona'])) {
            $data['persona'] = PersonaCatalog::inferFromWorkspaceType($workspace->type);
        }

        if (! isset($data['awareness_level'])) {
            $data['awareness_level'] = PersonaCatalog::defaultAwareness($data['persona']);
        }

        if (! isset($data['recommended_path']) && ! empty($data['primary_goal'])) {
            $data['recommended_path'] = PathCatalog::recommend(
                $data['persona'] ?? null,
                $data['primary_goal'] ?? null,
                $data['awareness_level'] ?? null,
            );
        }

        if (! isset($data['country'])) {
            $data['country'] = '';
        }

        if (! isset($data['content_locale']) || ! ContentLocaleCatalog::exists($data['content_locale'])) {
            $data['content_locale'] = 'ar_modern_fusha';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function put(Workspace $workspace, array $attributes): WorkspaceData
    {
        $current = $this->get($workspace);
        $payload = array_merge($current, array_filter(
            $attributes,
            fn (mixed $value) => $value !== null && $value !== ''
        ));

        if (! PersonaCatalog::exists($payload['persona'] ?? null)) {
            $payload['persona'] = PersonaCatalog::inferFromWorkspaceType($workspace->type);
        }

        if (empty($payload['awareness_level'])) {
            $payload['awareness_level'] = PersonaCatalog::defaultAwareness($payload['persona']);
        }

        if (
            empty($payload['recommended_path'])
            && ! empty($payload['primary_goal'])
        ) {
            $payload['recommended_path'] = PathCatalog::recommend(
                $payload['persona'] ?? null,
                $payload['primary_goal'] ?? null,
                $payload['awareness_level'] ?? null,
            );
        }

        return WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => null,
                'key' => self::PROFILE_KEY,
            ],
            [
                'value_json' => $payload,
            ],
        );
    }
}
