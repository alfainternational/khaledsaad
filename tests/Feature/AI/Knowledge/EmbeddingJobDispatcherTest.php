<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\EmbeddingJobDispatcher;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmbeddingJobDispatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_queues_bounded_idempotent_batches_for_missing_active_chunks(): void
    {
        config()->set('services.knowledge.embedding_batch_size', 2);
        $repository = app(StructuredKnowledgeRepository::class);
        foreach (range(1, 3) as $index) {
            $repository->storeDocument(
                KnowledgeScope::global(),
                'curated',
                'knowledge://dispatch-'.$index,
                'Document '.$index,
                'content '.$index,
                [['heading' => null, 'content' => 'content '.$index, 'locator' => []]],
                80,
            );
        }

        $dispatcher = app(EmbeddingJobDispatcher::class);
        $this->assertSame(2, $dispatcher->dispatch(100));
        $this->assertSame(0, $dispatcher->dispatch(100));

        $jobs = IntelligenceJob::query()->where('type', 'embeddings')->orderBy('id')->get();
        $this->assertCount(2, $jobs);
        $this->assertCount(2, $jobs[0]->payload_json['items']);
        $this->assertCount(1, $jobs[1]->payload_json['items']);
        $this->assertSame('queued', $jobs[0]->status);
    }
}
