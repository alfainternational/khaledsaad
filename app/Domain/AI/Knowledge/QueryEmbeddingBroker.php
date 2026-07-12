<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\AI\Knowledge\Models\KnowledgeQueryEmbedding;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use Illuminate\Support\Str;

class QueryEmbeddingBroker
{
    /** @return list<float>|null */
    public function findOrQueue(KnowledgeScope $scope, string $query): ?array
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        if ($normalized === '') {
            return null;
        }
        $model = (string) config('services.knowledge.embedding_model', 'nomic-embed-text');
        $version = (string) config('services.knowledge.embedding_model_version', 'v1');
        $queryHash = hash('sha256', $normalized);
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

        if (! (bool) config('services.private_worker.enabled', false) || $this->alreadyQueued($scope->key(), $queryHash)) {
            return null;
        }
        $payload = [
            'target' => 'query',
            'model_name' => $model,
            'model_version' => $version,
            'items' => [['scope_key' => $scope->key(), 'query_hash' => $queryHash, 'text' => $normalized]],
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
