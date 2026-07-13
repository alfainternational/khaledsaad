<?php

namespace App\Domain\AI\Knowledge\Uploads;

final readonly class ExtractedKnowledge
{
    /**
     * @param  list<array{heading: string|null, content: string, locator: array<string, mixed>}>  $chunks
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public array $chunks,
        public string $language,
        public array $metadata,
    ) {}
}
