<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class StructuredKnowledgeRepository
{
    public function storeDocument(
        KnowledgeScope $scope,
        string $kind,
        string $canonicalUri,
        string $title,
        string $content,
        array $chunks,
        int $trustScore = 50
    ): KnowledgeDocument {
        $document = $this->storeDocumentInternal(
            $scope,
            $kind,
            $canonicalUri,
            $title,
            $content,
            $chunks,
            $trustScore,
            null,
        );

        if ($document === null) {
            throw new LogicException('Unconditional knowledge storage was unexpectedly rejected.');
        }

        return $document;
    }

    public function markPendingGeneration(
        KnowledgeScope $scope,
        string $kind,
        string $canonicalUri,
        string $generation,
        int $trustScore = 50,
    ): void {
        $kind = trim($kind);
        $canonicalUri = trim($canonicalUri);
        $this->validateIdentity($kind, $canonicalUri);
        $this->validateGeneration($generation);

        if ($trustScore < 0 || $trustScore > 100) {
            throw new InvalidArgumentException('Knowledge trust score must be between 0 and 100.');
        }

        DB::transaction(function () use ($scope, $kind, $canonicalUri, $generation, $trustScore): void {
            $source = $this->sourceForUpdate($scope, $kind, $canonicalUri, $trustScore);
            $meta = is_array($source->meta_json) ? $source->meta_json : [];
            $meta['legacy_pending_generation'] = $generation;
            $source->update(['meta_json' => $meta]);
            $source->documents()->where('status', 'active')->update(['status' => 'superseded']);
        });
    }

    public function storePendingDocument(
        KnowledgeScope $scope,
        string $kind,
        string $canonicalUri,
        string $title,
        string $content,
        array $chunks,
        string $generation,
        int $trustScore = 50,
    ): ?KnowledgeDocument {
        $this->validateGeneration($generation);

        return $this->storeDocumentInternal(
            $scope,
            $kind,
            $canonicalUri,
            $title,
            $content,
            $chunks,
            $trustScore,
            $generation,
        );
    }

    private function storeDocumentInternal(
        KnowledgeScope $scope,
        string $kind,
        string $canonicalUri,
        string $title,
        string $content,
        array $chunks,
        int $trustScore,
        ?string $expectedGeneration,
    ): ?KnowledgeDocument {
        $kind = trim($kind);
        $canonicalUri = trim($canonicalUri);

        $this->validateIdentity($kind, $canonicalUri);

        if ($trustScore < 0 || $trustScore > 100) {
            throw new InvalidArgumentException('Knowledge trust score must be between 0 and 100.');
        }

        $content = $this->normalizeContent($content);
        $chunks = $this->normalizeChunks($chunks);
        $contentHash = hash('sha256', $content);
        $identityHash = $this->identityHash($scope, $kind, $canonicalUri);

        return DB::transaction(function () use (
            $scope,
            $kind,
            $canonicalUri,
            $title,
            $content,
            $chunks,
            $trustScore,
            $contentHash,
            $identityHash,
            $expectedGeneration,
        ): ?KnowledgeDocument {
            $source = $this->sourceForUpdate($scope, $kind, $canonicalUri, $trustScore, $identityHash);

            if ($expectedGeneration !== null
                && ($source->meta_json['legacy_pending_generation'] ?? null) !== $expectedGeneration) {
                return null;
            }

            $existing = $source->documents()->where('content_hash', $contentHash)->first();

            if ($existing !== null) {
                $this->assertChunkIntegrity($existing, $chunks);

                $source->documents()
                    ->where('status', 'active')
                    ->whereKeyNot($existing->id)
                    ->update(['status' => 'superseded']);

                if ($existing->status !== 'active') {
                    $existing->update(['status' => 'active']);
                }

                $this->markGenerationApplied($source, $expectedGeneration);

                return $existing->load(['source', 'chunks' => fn ($query) => $query->orderBy('position')]);
            }

            $source->documents()->where('status', 'active')->update(['status' => 'superseded']);

            $document = $source->documents()->create([
                'content_hash' => $contentHash,
                'version' => ((int) $source->documents()->max('version')) + 1,
                'title' => $title,
                'language' => 'ar',
                'status' => 'active',
                'content' => $content,
            ]);

            foreach ($chunks as $chunk) {
                $document->chunks()->create([
                    'position' => $chunk['position'],
                    'heading' => $chunk['heading'],
                    'content' => $chunk['content'],
                    'token_count' => $chunk['token_count'],
                    'locator_json' => $chunk['locator'],
                ]);
            }

            $this->markGenerationApplied($source, $expectedGeneration);

            return $document->load(['source', 'chunks' => fn ($query) => $query->orderBy('position')]);
        });
    }

    public function latestDocument(
        KnowledgeScope $scope,
        string $kind,
        string $canonicalUri
    ): ?KnowledgeDocument {
        $kind = trim($kind);
        $canonicalUri = trim($canonicalUri);
        $this->validateIdentity($kind, $canonicalUri);

        return KnowledgeDocument::query()
            ->inScope($scope)
            ->whereHas('source', fn ($query) => $query
                ->where('kind', $kind)
                ->where('canonical_uri', $canonicalUri))
            ->where('status', 'active')
            ->with(['source', 'chunks' => fn ($query) => $query->orderBy('position')])
            ->orderByDesc('version')
            ->first();
    }

    public function searchText(KnowledgeScope $scope, string $query, int $limit = 10): Collection
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Knowledge search limit must be between 1 and 100.');
        }

        $query = trim($query);

        if ($query === '') {
            return new Collection;
        }

        return $this->searchTerms($scope, [$query], $limit);
    }

    /** @param list<string> $terms */
    public function searchTerms(KnowledgeScope $scope, array $terms, int $limit = 10): Collection
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Knowledge search limit must be between 1 and 100.');
        }

        $terms = collect($terms)
            ->filter(fn ($term): bool => is_string($term) && trim($term) !== '')
            ->map(fn (string $term): string => trim($term))
            ->unique()
            ->take(8)
            ->values()
            ->all();
        if ($terms === []) {
            return new Collection;
        }

        $chunks = fn () => KnowledgeChunk::query()
            ->inScope($scope)
            ->whereHas('document', fn ($documentQuery) => $documentQuery->where('status', 'active'))
            ->with('document.source');

        if (DB::getDriverName() === 'sqlite') {
            return $chunks()
                ->where(function ($query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->orWhereRaw("content LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($term).'%']);
                    }
                })
                ->orderBy('id')
                ->limit($limit)
                ->get();
        }

        $fullTextQuery = implode(' ', $terms);
        $fullText = $chunks()
            ->select('knowledge_chunks.*')
            ->selectRaw('MATCH(content) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance', [$fullTextQuery])
            ->whereRaw('MATCH(content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$fullTextQuery])
            ->orderByDesc('relevance')
            ->orderBy('knowledge_chunks.id')
            ->limit($limit)
            ->get();

        if ($fullText->count() >= $limit) {
            return $fullText;
        }

        $literal = $chunks()
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhereRaw("content LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($term).'%']);
                }
            })
            ->whereNotIn('knowledge_chunks.id', $fullText->pluck('id'))
            ->orderBy('knowledge_chunks.id')
            ->limit($limit - $fullText->count())
            ->get();

        return $fullText->concat($literal)->values();
    }

    public function deactivateDocuments(KnowledgeScope $scope, string $kind, string $canonicalUri): int
    {
        $kind = trim($kind);
        $canonicalUri = trim($canonicalUri);
        $this->validateIdentity($kind, $canonicalUri);

        return DB::transaction(function () use ($scope, $kind, $canonicalUri): int {
            $source = KnowledgeSource::query()
                ->inScope($scope)
                ->where('kind', $kind)
                ->where('canonical_uri', $canonicalUri)
                ->lockForUpdate()
                ->first();

            if ($source === null) {
                return 0;
            }

            $this->assertSourceIntegrity($source, $scope, $kind, $canonicalUri);

            return $source->documents()->where('status', 'active')->update(['status' => 'superseded']);
        });
    }

    private function sourceForUpdate(
        KnowledgeScope $scope,
        string $kind,
        string $canonicalUri,
        int $trustScore,
        ?string $identityHash = null,
    ): KnowledgeSource {
        $source = KnowledgeSource::query()->firstOrCreate(
            [
                'scope_key' => $scope->key(),
                'identity_hash' => $identityHash ?? $this->identityHash($scope, $kind, $canonicalUri),
            ],
            [
                'account_id' => $scope->accountId,
                'workspace_id' => $scope->workspaceId,
                'project_id' => $scope->projectId,
                'kind' => $kind,
                'canonical_uri' => $canonicalUri,
                'trust_score' => $trustScore,
                'visibility' => $scope->visibility,
            ],
        );

        $source = KnowledgeSource::query()->lockForUpdate()->findOrFail($source->id);
        $this->assertSourceIntegrity($source, $scope, $kind, $canonicalUri);

        return $source;
    }

    private function markGenerationApplied(KnowledgeSource $source, ?string $generation): void
    {
        if ($generation === null) {
            return;
        }

        $meta = is_array($source->meta_json) ? $source->meta_json : [];
        unset($meta['legacy_pending_generation']);
        $meta['legacy_applied_generation'] = $generation;
        $source->update(['meta_json' => $meta]);
    }

    private function validateGeneration(string $generation): void
    {
        if (preg_match('/\A[0-9a-f]{64}\z/D', $generation) !== 1) {
            throw new InvalidArgumentException('Knowledge generation must be a SHA-256 hash.');
        }
    }

    private function normalizeContent(string $content): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $content));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private function validateIdentity(string $kind, string $canonicalUri): void
    {
        if ($kind === '' || $canonicalUri === '') {
            throw new InvalidArgumentException('Knowledge kind and canonical URI must not be blank.');
        }
    }

    private function normalizeChunks(array $chunks): array
    {
        return array_map(function ($chunk, int $position): array {
            if (! is_array($chunk) || ! array_key_exists('content', $chunk) || ! is_string($chunk['content'])) {
                throw new InvalidArgumentException('Each knowledge chunk must contain text content.');
            }

            $content = $this->normalizeContent($chunk['content']);

            if ($content === '') {
                throw new InvalidArgumentException('Knowledge chunk content must not be blank.');
            }

            $heading = $chunk['heading'] ?? null;

            if ($heading !== null && ! is_string($heading)) {
                throw new InvalidArgumentException('Knowledge chunk heading must be text or null.');
            }

            $locator = $chunk['locator'] ?? [];

            if (! is_array($locator)) {
                throw new InvalidArgumentException('Knowledge chunk locator must be an array.');
            }

            return [
                'position' => $position,
                'heading' => $heading,
                'content' => $content,
                'token_count' => max(1, (int) ceil(mb_strlen($content) / 4)),
                'locator' => $locator,
            ];
        }, array_values($chunks), array_keys(array_values($chunks)));
    }

    private function assertChunkIntegrity(KnowledgeDocument $document, array $expectedChunks): void
    {
        $persistedChunks = $document->chunks()->orderBy('position')->get();

        if ($persistedChunks->count() !== count($expectedChunks)) {
            throw new LogicException('Knowledge document chunks do not match the stored content.');
        }

        foreach ($expectedChunks as $index => $expected) {
            $persisted = $persistedChunks->get($index);

            if (
                $persisted === null
                || $persisted->position !== $expected['position']
                || $persisted->heading !== $expected['heading']
                || $persisted->content !== $expected['content']
                || $persisted->token_count !== $expected['token_count']
                || $persisted->locator_json != $expected['locator']
            ) {
                throw new LogicException('Knowledge document chunks do not match the stored content.');
            }
        }
    }

    private function identityHash(KnowledgeScope $scope, string $kind, string $canonicalUri): string
    {
        return hash('sha256', implode('|', [
            $scope->visibility,
            $scope->accountId ?? 'global',
            $scope->workspaceId ?? 'global',
            $scope->projectId ?? 'global',
            $kind,
            $canonicalUri,
        ]));
    }

    private function assertSourceIntegrity(
        KnowledgeSource $source,
        KnowledgeScope $scope,
        string $kind,
        string $canonicalUri
    ): void {
        if (
            $source->account_id !== $scope->accountId
            || $source->workspace_id !== $scope->workspaceId
            || $source->project_id !== $scope->projectId
            || $source->visibility !== $scope->visibility
            || $source->scope_key !== $scope->key()
            || $source->kind !== $kind
            || $source->canonical_uri !== $canonicalUri
        ) {
            throw new LogicException('Knowledge source identity is corrupt.');
        }
    }
}
