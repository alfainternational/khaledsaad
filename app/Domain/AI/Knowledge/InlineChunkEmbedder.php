<?php

namespace App\Domain\AI\Knowledge;

use App\Contracts\EmbeddingsGateway;
use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use App\Domain\AI\Knowledge\Models\KnowledgeEmbedding;
use Illuminate\Support\Facades\Log;

/**
 * تضمين مقاطع المعرفة من الخادم مباشرة (بديل العامل الخارجي): يجد المقاطع
 * النشطة بلا متجه حديث لهوية النموذج الحالية، يضمّنها دفعات عبر الـAPI،
 * ويخزّنها في knowledge_embeddings — فيصبح الاسترجاع الهجين حياً بلا عامل.
 */
class InlineChunkEmbedder
{
    public function __construct(
        private readonly EmbeddingsGateway $embeddings,
        private readonly VectorMath $vectorMath,
    ) {}

    /** @return array{embedded: int, failed: int} */
    public function embedPending(int $limit = 200): array
    {
        if (! EmbeddingIdentity::inlineApiActive()) {
            return ['embedded' => 0, 'failed' => 0];
        }

        $limit = max(1, min(1000, $limit));
        $model = EmbeddingIdentity::modelName();
        $version = EmbeddingIdentity::modelVersion();
        $batchSize = max(1, min(64, (int) config('services.knowledge.embedding_batch_size', 16)));

        $pending = [];
        KnowledgeChunk::query()
            ->with(['embeddings' => fn ($query) => $query
                ->where('model_name', $model)
                ->where('model_version', $version)
                ->where('status', 'active')])
            ->whereHas('document', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->chunkById(100, function ($chunks) use (&$pending, $limit): bool {
                foreach ($chunks as $chunk) {
                    $hash = hash('sha256', $chunk->content);
                    if ($chunk->embeddings->contains(fn ($embedding) => hash_equals($hash, $embedding->content_hash))) {
                        continue;
                    }
                    $pending[] = ['chunk_id' => (int) $chunk->id, 'content_hash' => $hash, 'text' => (string) $chunk->content];
                    if (count($pending) >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        $embedded = 0;
        $failed = 0;
        foreach (array_chunk($pending, $batchSize) as $batch) {
            $vectors = $this->embeddings->embed(array_column($batch, 'text'), 'passage');
            if (! is_array($vectors) || count($vectors) !== count($batch)) {
                $failed += count($batch);

                continue;
            }
            foreach ($batch as $index => $item) {
                try {
                    $normalized = $this->vectorMath->normalize($vectors[$index]);
                } catch (\InvalidArgumentException $e) {
                    Log::warning('Inline chunk embedding rejected: '.$e->getMessage(), ['chunk_id' => $item['chunk_id']]);
                    $failed++;

                    continue;
                }
                KnowledgeEmbedding::query()->updateOrCreate(
                    ['knowledge_chunk_id' => $item['chunk_id'], 'model_name' => $model, 'model_version' => $version],
                    [
                        'dimensions' => count($normalized),
                        'content_hash' => $item['content_hash'],
                        'vector_json' => $normalized,
                        'status' => 'active',
                    ],
                );
                $embedded++;
            }
        }

        return ['embedded' => $embedded, 'failed' => $failed];
    }
}
