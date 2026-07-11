<?php

namespace App\Domain\AI\Knowledge;

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
