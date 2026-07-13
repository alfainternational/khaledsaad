<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeEmbedding;
use App\Domain\AI\Knowledge\Models\KnowledgeQueryEmbedding;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\WorkerProtocolException;
use App\Domain\AI\Worker\WorkerResultApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmbeddingWorkerResultTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_validates_and_stores_normalized_chunk_embeddings_idempotently(): void
    {
        [$job, $worker, $chunkId, $contentHash] = $this->embeddingJob();
        $result = [
            'model_name' => 'nomic-embed-text',
            'model_version' => 'v1',
            'vectors' => [['chunk_id' => $chunkId, 'content_hash' => $contentHash, 'vector' => [3, 4]]],
        ];

        app(WorkerResultApplier::class)->apply($job, $worker, $result);
        app(WorkerResultApplier::class)->apply($job, $worker, $result);

        $embedding = KnowledgeEmbedding::query()->sole();
        $this->assertSame(2, $embedding->dimensions);
        $this->assertSame([0.6, 0.8], $embedding->vector_json);
        $this->assertSame('active', $embedding->status);
    }

    #[Test]
    public function it_rejects_results_that_do_not_match_the_job_contract(): void
    {
        [$job, $worker, $chunkId] = $this->embeddingJob();

        $this->expectException(WorkerProtocolException::class);
        app(WorkerResultApplier::class)->apply($job, $worker, [
            'model_name' => 'different-model',
            'model_version' => 'v1',
            'vectors' => [['chunk_id' => $chunkId, 'content_hash' => str_repeat('0', 64), 'vector' => [1, 2]]],
        ]);
    }

    #[Test]
    public function it_caches_query_embeddings_only_for_the_signed_job_scope(): void
    {
        $scope = KnowledgeScope::global();
        $queryHash = hash('sha256', 'retention query');
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'type' => 'embeddings',
            'status' => 'leased',
            'payload_json' => [
                'target' => 'query',
                'model_name' => 'nomic-embed-text',
                'model_version' => 'v1',
                'items' => [['scope_key' => $scope->key(), 'query_hash' => $queryHash, 'text' => 'retention query']],
            ],
        ]);
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => 'Query Worker',
            'secret_ciphertext' => Crypt::encryptString(Str::random(64)),
            'capabilities_json' => ['embeddings'],
            'status' => 'active',
        ]);

        app(WorkerResultApplier::class)->apply($job, $worker, [
            'model_name' => 'nomic-embed-text',
            'model_version' => 'v1',
            'vectors' => [['scope_key' => $scope->key(), 'query_hash' => $queryHash, 'vector' => [1, 0]]],
        ]);

        $cached = KnowledgeQueryEmbedding::query()->sole();
        $this->assertSame($scope->key(), $cached->scope_key);
        $this->assertTrue($cached->expires_at->isFuture());
    }

    /** @return array{IntelligenceJob, IntelligenceWorker, int, string} */
    private function embeddingJob(): array
    {
        $document = app(StructuredKnowledgeRepository::class)->storeDocument(
            KnowledgeScope::global(),
            'curated',
            'knowledge://embedding-test',
            'Embedding test',
            'semantic retrieval content',
            [['heading' => 'Test', 'content' => 'semantic retrieval content', 'locator' => []]],
            90,
        );
        $chunk = $document->chunks()->sole();
        $contentHash = hash('sha256', $chunk->content);
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'type' => 'embeddings',
            'status' => 'leased',
            'payload_json' => [
                'model_name' => 'nomic-embed-text',
                'model_version' => 'v1',
                'items' => [['chunk_id' => $chunk->id, 'content_hash' => $contentHash, 'text' => $chunk->content]],
            ],
            'available_at' => now(),
        ]);
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => 'Embedding Worker',
            'secret_ciphertext' => Crypt::encryptString(Str::random(64)),
            'capabilities_json' => ['embeddings'],
            'status' => 'active',
        ]);

        return [$job, $worker, $chunk->id, $contentHash];
    }
}
