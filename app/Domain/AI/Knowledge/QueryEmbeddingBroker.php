<?php

namespace App\Domain\AI\Knowledge;

use App\Contracts\EmbeddingsGateway;
use App\Domain\AI\Knowledge\Models\KnowledgeQueryEmbedding;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use Illuminate\Support\Str;

class QueryEmbeddingBroker
{
    public function __construct(
        private readonly EmbeddingsGateway $embeddings,
        private readonly VectorMath $vectorMath,
    ) {}

    /** @return list<float>|null */
    public function findOrQueue(KnowledgeScope $scope, string $query): ?array
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        if ($normalized === '') {
            return null;
        }
        $model = EmbeddingIdentity::modelName();
        $version = EmbeddingIdentity::modelVersion();
        $instruction = trim((string) config('services.knowledge.embedding_query_instruction', ''));
        $modelInput = $instruction === ''
            ? $normalized
            : "Instruct: {$instruction}\nQuery: {$normalized}";
        $queryHash = hash('sha256', $modelInput);
        $cached = KnowledgeQueryEmbedding::query()
            ->where('scope_key', $scope->key())
            ->where('query_hash', $queryHash)
            ->where('model_name', $model)
            ->where('model_version', $version)
            ->where('expires_at', '>', now())
            ->first();
        if ($cached) {
            return $cached->vector_json;
        }

        // المسار المضمّن: تضمين فوري من الخادم عبر API (بلا عامل خارجي).
        if (EmbeddingIdentity::inlineApiActive()) {
            $vectors = $this->embeddings->embed([$modelInput], 'query');
            $vector = $vectors[0] ?? null;
            if (! is_array($vector)) {
                return null;
            }
            try {
                $normalizedVector = $this->vectorMath->normalize($vector);
            } catch (\InvalidArgumentException) {
                return null;
            }
            KnowledgeQueryEmbedding::query()->updateOrCreate(
                ['scope_key' => $scope->key(), 'query_hash' => $queryHash, 'model_name' => $model, 'model_version' => $version],
                [
                    'dimensions' => count($normalizedVector),
                    'vector_json' => $normalizedVector,
                    'expires_at' => now()->addDays((int) config('services.knowledge.query_embedding_ttl_days', 7)),
                ],
            );

            return $normalizedVector;
        }

        if (! (bool) config('services.private_worker.enabled', false) || $this->alreadyQueued($scope->key(), $queryHash)) {
            return null;
        }
        $payload = [
            'target' => 'query',
            'model_name' => $model,
            'model_version' => $version,
            'items' => [['scope_key' => $scope->key(), 'query_hash' => $queryHash, 'text' => $modelInput]],
        ];
        IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $scope->accountId,
            'workspace_id' => $scope->workspaceId,
            'project_id' => $scope->projectId,
            'type' => 'embeddings',
            'status' => 'queued',
            'payload_json' => $payload,
            'input_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'available_at' => now(),
            'timeout_seconds' => 120,
            'max_attempts' => 2,
        ]);

        return null;
    }

    private function alreadyQueued(string $scopeKey, string $queryHash): bool
    {
        return IntelligenceJob::query()
            ->where('type', 'embeddings')
            ->whereIn('status', ['queued', 'leased'])
            ->get(['payload_json'])
            ->contains(function (IntelligenceJob $job) use ($scopeKey, $queryHash): bool {
                foreach ((array) ($job->payload_json['items'] ?? []) as $item) {
                    if (is_array($item) && ($item['scope_key'] ?? null) === $scopeKey && ($item['query_hash'] ?? null) === $queryHash) {
                        return true;
                    }
                }

                return false;
            });
    }
}
