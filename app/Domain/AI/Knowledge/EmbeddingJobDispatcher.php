<?php

namespace App\Domain\AI\Knowledge;

use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use Illuminate\Support\Str;

class EmbeddingJobDispatcher
{
    public function dispatch(int $limit = 100): int
    {
        $limit = max(1, min(1000, $limit));
        $model = (string) config('services.knowledge.embedding_model', 'nomic-embed-text');
        $version = (string) config('services.knowledge.embedding_model_version', 'v1');
        $batchSize = max(1, min(64, (int) config('services.knowledge.embedding_batch_size', 16)));
        $pending = $this->pendingChunkIds();
        $groups = [];
        $selected = 0;

        KnowledgeChunk::query()
            ->with(['document.source', 'embeddings' => fn ($query) => $query
                ->where('model_name', $model)
                ->where('model_version', $version)
                ->where('status', 'active')])
            ->whereHas('document', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->chunkById(100, function ($chunks) use (&$groups, &$selected, $limit, $pending): bool {
                foreach ($chunks as $chunk) {
                    $hash = hash('sha256', $chunk->content);
                    if (isset($pending[$chunk->id]) || $chunk->embeddings->contains(fn ($embedding) => hash_equals($hash, $embedding->content_hash))) {
                        continue;
                    }
                    $source = $chunk->document->source;
                    $key = implode(':', [$source->account_id ?? '-', $source->workspace_id ?? '-', $source->project_id ?? '-']);
                    $groups[$key]['tenant'] = [$source->account_id, $source->workspace_id, $source->project_id];
                    $groups[$key]['items'][] = ['chunk_id' => $chunk->id, 'content_hash' => $hash, 'text' => $chunk->content];
                    $selected++;
                    if ($selected >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        $created = 0;
        foreach ($groups as $group) {
            foreach (array_chunk($group['items'], $batchSize) as $items) {
                [$accountId, $workspaceId, $projectId] = $group['tenant'];
                $payload = ['model_name' => $model, 'model_version' => $version, 'items' => $items];
                IntelligenceJob::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'account_id' => $accountId,
                    'workspace_id' => $workspaceId,
                    'project_id' => $projectId,
                    'type' => 'embeddings',
                    'status' => 'queued',
                    'payload_json' => $payload,
                    'input_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'available_at' => now(),
                    'timeout_seconds' => 300,
                    'max_attempts' => 3,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** @return array<int, true> */
    private function pendingChunkIds(): array
    {
        $ids = [];
        IntelligenceJob::query()
            ->where('type', 'embeddings')
            ->whereIn('status', ['queued', 'leased'])
            ->get(['payload_json'])
            ->each(function (IntelligenceJob $job) use (&$ids): void {
                foreach ((array) ($job->payload_json['items'] ?? []) as $item) {
                    if (is_array($item) && is_int($item['chunk_id'] ?? null)) {
                        $ids[$item['chunk_id']] = true;
                    }
                }
            });

        return $ids;
    }
}
