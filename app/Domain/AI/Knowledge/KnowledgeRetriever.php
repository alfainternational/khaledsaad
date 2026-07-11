<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class KnowledgeRetriever
{
    public function __construct(private readonly StructuredKnowledgeRepository $repository) {}

    /** @return Collection<int, KnowledgeEvidence> */
    public function retrieve(KnowledgeScope $scope, string $query, int $limit = 8): Collection
    {
        if ($limit < 1 || $limit > 50) {
            throw new InvalidArgumentException('Knowledge retrieval limit must be between 1 and 50.');
        }

        $terms = $this->terms($query);
        if ($terms === []) {
            return collect();
        }

        $matches = [];
        foreach ($this->searchScopes($scope) as [$searchScope, $scopeRank]) {
            foreach ($terms as $term) {
                foreach ($this->repository->searchText($searchScope, $term, min(100, $limit * 4)) as $chunk) {
                    $id = (int) $chunk->id;
                    $matches[$id] ??= ['chunk' => $chunk, 'scope_rank' => $scopeRank, 'terms' => []];
                    $matches[$id]['terms'][$term] = true;
                }
            }
        }

        return collect($matches)
            ->map(function (array $match): KnowledgeEvidence {
                /** @var KnowledgeChunk $chunk */
                $chunk = $match['chunk'];
                $document = $chunk->document;
                $source = $document->source;
                $termScore = count($match['terms']) * 100;
                $scopeScore = (int) $match['scope_rank'] * 10;
                $trust = (int) $source->trust_score;

                return new KnowledgeEvidence(
                    chunkId: (int) $chunk->id,
                    citation: sprintf('[KB:%d:%d:%d]', $source->id, $document->version, $chunk->position),
                    sourceTitle: trim((string) $document->title) ?: $source->kind,
                    sourceKind: $source->kind,
                    sourceUri: (string) $source->canonical_uri,
                    visibility: $source->visibility,
                    trustScore: $trust,
                    heading: (string) ($chunk->heading ?? ''),
                    excerpt: Str::limit(trim($chunk->content), 900, ''),
                    locator: is_array($chunk->locator_json) ? $chunk->locator_json : [],
                    score: $termScore + $scopeScore + $trust,
                );
            })
            ->sortBy([
                ['score', 'desc'],
                ['chunkId', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    /** @return list<array{KnowledgeScope, int}> */
    private function searchScopes(KnowledgeScope $scope): array
    {
        return match ($scope->visibility) {
            'project' => [
                [$scope, 3],
                [KnowledgeScope::forWorkspace($scope->accountId, $scope->workspaceId), 2],
                [KnowledgeScope::global(), 1],
            ],
            'workspace' => [
                [$scope, 2],
                [KnowledgeScope::global(), 1],
            ],
            'global' => [[$scope, 1]],
        };
    }

    /** @return list<string> */
    private function terms(string $query): array
    {
        $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $query) ?? '');
        $stopWords = ['هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'على', 'إلى', 'الى', 'عن', 'من', 'في', 'مع', 'the', 'and', 'for'];

        return collect(preg_split('/\s+/u', trim($normalized)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3 && ! in_array($term, $stopWords, true))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }
}
