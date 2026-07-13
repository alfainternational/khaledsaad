<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use InvalidArgumentException;

final readonly class KnowledgeScope
{
    public function __construct(
        public ?int $accountId,
        public ?int $workspaceId,
        public ?int $projectId,
        public string $visibility,
    ) {
        if (! in_array($visibility, ['project', 'workspace', 'global'], true)) {
            throw new InvalidArgumentException("Unknown knowledge visibility [{$visibility}].");
        }

        foreach ([$accountId, $workspaceId, $projectId] as $id) {
            if ($id !== null && $id <= 0) {
                throw new InvalidArgumentException('Knowledge tenant identifiers must be positive.');
            }
        }

        $valid = match ($visibility) {
            'project' => $accountId !== null && $workspaceId !== null && $projectId !== null,
            'workspace' => $accountId !== null && $workspaceId !== null && $projectId === null,
            'global' => $accountId === null && $workspaceId === null && $projectId === null,
        };

        if (! $valid) {
            throw new InvalidArgumentException("Invalid tenant identifiers for [{$visibility}] visibility.");
        }
    }

    public static function forProject(int $accountId, int $workspaceId, int $projectId): self
    {
        return new self($accountId, $workspaceId, $projectId, 'project');
    }

    public static function forWorkspace(int $accountId, int $workspaceId): self
    {
        return new self($accountId, $workspaceId, null, 'workspace');
    }

    public static function fromWorkspace(Workspace $workspace): self
    {
        return self::forWorkspace((int) $workspace->account_id, (int) $workspace->id);
    }

    public static function fromProject(Project $project): self
    {
        $workspace = $project->relationLoaded('workspace')
            ? $project->getRelation('workspace')
            : $project->workspace()->first();

        if (! $workspace instanceof Workspace || (int) $project->workspace_id !== (int) $workspace->id) {
            throw new InvalidArgumentException('Project workspace hierarchy is invalid.');
        }

        return self::forProject((int) $workspace->account_id, (int) $workspace->id, (int) $project->id);
    }

    public static function global(): self
    {
        return new self(null, null, null, 'global');
    }

    public function key(): string
    {
        return hash('sha256', implode('|', [
            $this->visibility,
            $this->accountId ?? 'global',
            $this->workspaceId ?? 'global',
            $this->projectId ?? 'global',
        ]));
    }
}
