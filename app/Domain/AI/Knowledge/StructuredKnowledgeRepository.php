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
        $kind = trim($kind);
        $canonicalUri = trim($canonicalUri);

        $this->validateIdentity($kind, $canonicalUri);

        if ($trustScore < 0 || $trustScore > 100) {
            throw new InvalidArgumentException('Knowledge trust score must be between 0 and 100.');
        }

        $content = $this->normalizeContent($content);
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
            $identityHash
        ): KnowledgeDocument {
            $source = KnowledgeSource::query()->firstOrCreate(
                [
                    'scope_key' => $scope->key(),
                    'identity_hash' => $identityHash,
                ],
                [
                    'account_id' => $scope->accountId,
                    'workspace_id' => $scope->workspaceId,
                    'project_id' => $scope->projectId,
                    'kind' => $kind,
                    'canonical_uri' => $canonicalUri,
                    'trust_score' => $trustScore,
                    'visibility' => $scope->visibility,
                ]
            );

            $source = KnowledgeSource::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertSourceIntegrity($source, $scope, $kind, $canonicalUri);

            $existing = $source->documents()->where('content_hash', $contentHash)->first();

            if ($existing !== null) {
                return $existing->load(['source', 'chunks' => fn ($query) => $query->orderBy('position')]);
            }

            $document = $source->documents()->create([
                'content_hash' => $contentHash,
                'version' => ((int) $source->documents()->max('version')) + 1,
                'title' => $title,
                'language' => 'ar',
                'status' => 'active',
                'content' => $content,
            ]);

            foreach (array_values($chunks) as $position => $chunk) {
                if (! is_array($chunk) || ! array_key_exists('content', $chunk) || ! is_string($chunk['content'])) {
                    throw new InvalidArgumentException('Each knowledge chunk must contain text content.');
                }

                $chunkContent = $this->normalizeContent($chunk['content']);

                if ($chunkContent === '') {
                    throw new InvalidArgumentException('Knowledge chunk content must not be blank.');
                }

                $locator = $chunk['locator'] ?? [];

                if (! is_array($locator)) {
                    throw new InvalidArgumentException('Knowledge chunk locator must be an array.');
                }

                $document->chunks()->create([
                    'position' => $position,
                    'heading' => $chunk['heading'] ?? null,
                    'content' => $chunkContent,
                    'token_count' => max(1, (int) ceil(mb_strlen($chunkContent) / 4)),
                    'locator_json' => $locator,
                ]);
            }

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

        $chunks = KnowledgeChunk::query()
            ->inScope($scope)
            ->whereHas('document', fn ($documentQuery) => $documentQuery->where('status', 'active'))
            ->with('document.source')
            ->limit($limit);

        if (DB::getDriverName() === 'sqlite') {
            $literalQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

            return $chunks
                ->whereRaw("content LIKE ? ESCAPE '\\'", ["%{$literalQuery}%"])
                ->orderBy('id')
                ->get();
        }

        return $chunks
            ->select('knowledge_chunks.*')
            ->selectRaw('MATCH(content) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance', [$query])
            ->whereRaw('MATCH(content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$query])
            ->orderByDesc('relevance')
            ->get();
    }

    private function normalizeContent(string $content): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $content));
    }

    private function validateIdentity(string $kind, string $canonicalUri): void
    {
        if ($kind === '' || $canonicalUri === '') {
            throw new InvalidArgumentException('Knowledge kind and canonical URI must not be blank.');
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
