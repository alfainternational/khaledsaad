<?php

namespace App\Domain\AI\Knowledge;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class KnowledgeEvidence implements Arrayable
{
    /** @param array<string, mixed> $locator */
    public function __construct(
        public int $chunkId,
        public string $citation,
        public string $sourceTitle,
        public string $sourceKind,
        public string $sourceUri,
        public string $visibility,
        public int $trustScore,
        public string $heading,
        public string $excerpt,
        public array $locator,
        public int $score,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
