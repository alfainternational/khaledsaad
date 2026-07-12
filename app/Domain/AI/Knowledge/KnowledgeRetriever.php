<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use App\Domain\AI\Knowledge\Models\KnowledgeEmbedding;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class KnowledgeRetriever
{
    public function __construct(
        private readonly StructuredKnowledgeRepository $repository,
        private readonly QueryEmbeddingBroker $queryEmbeddings,
        private readonly VectorMath $vectorMath,
    ) {}

    /** @return Collection<int, KnowledgeEvidence> */
    public function retrieve(KnowledgeScope $scope, string $query, int $limit = 8): Collection
    {
        if ($limit < 1 || $limit > 50) {
            throw new InvalidArgumentException('Knowledge retrieval limit must be between 1 and 50.');
        }

        $terms = $this->terms($query);
        $hybrid = (bool) config('services.knowledge.hybrid_retrieval', false);
        if ($terms === [] && ! $hybrid) {
            return collect();
        }

        $matches = [];
        if ($terms !== []) {
            foreach ($this->searchScopes($scope) as [$searchScope, $scopeRank]) {
                foreach ($this->repository->searchTerms($searchScope, $terms, min(100, $limit * 4)) as $chunk) {
                    $id = (int) $chunk->id;
                    $matches[$id] ??= ['chunk' => $chunk, 'scope_rank' => $scopeRank, 'terms' => []];
                    foreach ($terms as $term) {
                        if (mb_stripos($chunk->content, $term) !== false) {
                            $matches[$id]['terms'][$term] = true;
                        }
                    }
                }
            }
        }

        $queryVector = $hybrid
            ? $this->queryEmbeddings->findOrQueue($scope, $query)
            : null;
        if (is_array($queryVector)) {
            $this->addSemanticMatches($matches, $scope, $queryVector);
        }

        return collect($matches)
            ->map(function (array $match): KnowledgeEvidence {
                /** @var KnowledgeChunk $chunk */
                $chunk = $match['chunk'];
                $document = $chunk->document;
                $source = $document->source;
                $termScore = count($match['terms']) * 100;
                $semanticScore = (int) round(max(0.0, (float) ($match['semantic'] ?? 0.0)) * 350);
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
                    score: $termScore + $semanticScore + $scopeScore + $trust,
                );
            })
            ->sortBy([
                ['score', 'desc'],
                ['chunkId', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    /** @param array<int, array{chunk: KnowledgeChunk, scope_rank: int, terms: array<string, true>, semantic?: float}> $matches
     * @param  list<float>  $queryVector
     */
    private function addSemanticMatches(array &$matches, KnowledgeScope $scope, array $queryVector): void
    {
        $model = (string) config('services.knowledge.embedding_model', 'nomic-embed-text');
        $version = (string) config('services.knowledge.embedding_model_version', 'v1');
        $limit = max(1, min(1000, (int) config('services.knowledge.embedding_candidate_limit', 200)));
        foreach ($this->searchScopes($scope) as [$searchScope, $scopeRank]) {
            KnowledgeEmbedding::query()
                ->with('chunk.document.source')
                ->where('model_name', $model)
                ->where('model_version', $version)
                ->where('status', 'active')
                ->whereHas('chunk', fn ($query) => $query
                    ->inScope($searchScope)
                    ->whereHas('document', fn ($document) => $document->where('status', 'active')))
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->each(function (KnowledgeEmbedding $embedding) use (&$matches, $queryVector, $scopeRank): void {
                    $chunk = $embedding->chunk;
                    if (! hash_equals($embedding->content_hash, hash('sha256', $chunk->content))) {
                        return;
                    }
                    try {
                        $cosine = $this->vectorMath->cosine($queryVector, $embedding->vector_json);
                    } catch (InvalidArgumentException) {
                        return;
                    }
                    if ($cosine < (float) config('services.knowledge.embedding_min_similarity', 0.25)) {
                        return;
                    }
                    $id = (int) $chunk->id;
                    $matches[$id] ??= ['chunk' => $chunk, 'scope_rank' => $scopeRank, 'terms' => []];
                    $matches[$id]['semantic'] = max((float) ($matches[$id]['semantic'] ?? -1.0), ($cosine + 1.0) / 2.0);
                    $matches[$id]['scope_rank'] = max($matches[$id]['scope_rank'], $scopeRank);
                });
        }
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
