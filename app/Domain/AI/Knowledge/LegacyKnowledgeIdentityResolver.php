<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;

final class LegacyKnowledgeIdentityResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{scope: KnowledgeScope|null, canonical_uri: string, key_hash: string, failure_reason: string|null}
     */
    public function resolve(string $key, array $data): array
    {
        $keyHash = hash('sha256', $key);
        $identity = [
            'scope' => null,
            'canonical_uri' => 'legacy://sha256/'.$keyHash,
            'key_hash' => $keyHash,
            'failure_reason' => 'scope_unresolved',
        ];

        if ($this->isGlobalKey($key)) {
            if ($this->containsTenantIdentifier($data)) {
                $identity['failure_reason'] = 'tenant_data_not_global';

                return $identity;
            }

            $identity['scope'] = KnowledgeScope::global();
            $identity['failure_reason'] = null;

            return $identity;
        }

        if (preg_match('/\Amonitor\.performance\.ws([1-9]\d*)\z/D', $key, $matches) === 1) {
            $identity['scope'] = $this->workspaceScope($data, (int) $matches[1]);
            $identity['failure_reason'] = $identity['scope'] === null ? 'scope_unresolved' : null;

            return $identity;
        }

        if (preg_match('/\Aagent\.[a-z0-9._-]+\.ws([1-9]\d*)\.[a-z0-9._-]+\z/D', $key, $matches) !== 1) {
            return $identity;
        }

        $workspaceId = (int) $matches[1];
        $workspaceScope = $this->workspaceScope($data, $workspaceId);

        if ($workspaceScope === null || ! array_key_exists('project_id', $data) || $data['project_id'] === null) {
            $identity['scope'] = $workspaceScope;
            $identity['failure_reason'] = $workspaceScope === null ? 'scope_unresolved' : null;

            return $identity;
        }

        if (! is_int($data['project_id']) || $data['project_id'] <= 0) {
            return $identity;
        }

        $project = Project::query()
            ->whereKey($data['project_id'])
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($project === null) {
            return $identity;
        }

        $identity['scope'] = KnowledgeScope::forProject(
            $workspaceScope->accountId,
            $workspaceScope->workspaceId,
            (int) $project->id,
        );
        $identity['failure_reason'] = null;

        return $identity;
    }

    /** @param array<string, mixed> $data */
    private function workspaceScope(array $data, int $workspaceId): ?KnowledgeScope
    {
        if (($data['workspace_id'] ?? null) !== $workspaceId) {
            return null;
        }

        $workspace = Workspace::query()->find($workspaceId);

        return $workspace !== null && (int) $workspace->account_id > 0
            ? KnowledgeScope::fromWorkspace($workspace)
            : null;
    }

    private function isGlobalKey(string $key): bool
    {
        return $key === 'patterns.global'
            || preg_match('/\A(?:playbook|teach|web)\.[a-z0-9][a-z0-9._-]*\z/D', $key) === 1;
    }

    /** @param array<string, mixed> $data */
    private function containsTenantIdentifier(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), [
                'account_id',
                'workspace_id',
                'project_id',
                'client_id',
                'user_id',
            ], true)) {
                return true;
            }

            if (is_array($value) && $this->containsTenantIdentifier($value)) {
                return true;
            }
        }

        return false;
    }
}
