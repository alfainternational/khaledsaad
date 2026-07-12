<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\IntelligenceEvaluationCase;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncKnowledgeEvaluationCasesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_synchronizes_curated_cases_idempotently(): void
    {
        $uri = 'knowledge://curated-evaluation';
        $document = app(StructuredKnowledgeRepository::class)->storeDocument(
            KnowledgeScope::global(),
            'curated',
            $uri,
            'Curated evaluation',
            'expected evidence',
            [['heading' => null, 'content' => 'expected evidence', 'locator' => []]],
            90,
        );
        config()->set('knowledge_evaluation.cases', [[
            'query' => 'Where is the expected evidence?',
            'expected_source_uri' => $uri,
            'minimum_rank' => 3,
        ]]);

        $this->artisan('knowledge:sync-evaluation-cases')->assertSuccessful();
        $firstPublicId = IntelligenceEvaluationCase::query()->sole()->public_id;
        $this->artisan('knowledge:sync-evaluation-cases')->assertSuccessful();

        $case = IntelligenceEvaluationCase::query()->sole();
        $this->assertSame($firstPublicId, $case->public_id);
        $this->assertSame($document->chunks()->sole()->id, $case->expected_chunk_id);
        $this->assertSame('curated_config', $case->meta_json['origin']);
    }
}
