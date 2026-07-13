<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\QueryEmbeddingBroker;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueryEmbeddingBrokerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_includes_the_retrieval_instruction_in_query_identity_and_payload(): void
    {
        config()->set('services.private_worker.enabled', true);
        config()->set('services.knowledge.embedding_query_instruction', 'Retrieve relevant Arabic business evidence');
        $query = 'كيف أحسن تجربة العميل؟';

        $broker = app(QueryEmbeddingBroker::class);
        $this->assertNull($broker->findOrQueue(KnowledgeScope::global(), $query));
        $this->assertNull($broker->findOrQueue(KnowledgeScope::global(), $query));

        $job = IntelligenceJob::query()->where('type', 'embeddings')->sole();
        $text = $job->payload_json['items'][0]['text'];
        $this->assertSame(
            "Instruct: Retrieve relevant Arabic business evidence\nQuery: {$query}",
            $text,
        );
        $this->assertSame(hash('sha256', $text), $job->payload_json['items'][0]['query_hash']);
    }
}
