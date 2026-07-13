<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\IntelligenceEvaluationCase;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvaluateKnowledgeRetrievalCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_repeatable_retrieval_quality_metrics(): void
    {
        $scope = KnowledgeScope::global();
        $document = app(StructuredKnowledgeRepository::class)->storeDocument(
            $scope,
            'curated',
            'knowledge://evaluation',
            'Evaluation source',
            'دليل موثق عن الاحتفاظ بالعملاء',
            [['heading' => null, 'content' => 'دليل موثق عن الاحتفاظ بالعملاء', 'locator' => []]],
            90,
        );
        IntelligenceEvaluationCase::query()->create([
            'public_id' => (string) Str::uuid(),
            'scope_key' => $scope->key(),
            'visibility' => 'global',
            'query' => 'الاحتفاظ بالعملاء',
            'expected_chunk_id' => $document->chunks()->sole()->id,
            'minimum_rank' => 3,
            'status' => 'active',
        ]);

        $this->artisan('knowledge:evaluate-retrieval', ['--strict' => true])->assertSuccessful();

        // الاسترجاع الهجين هو الافتراضي الآن (يتدهور داخلياً للمعجمي بلا متجهات).
        $this->assertDatabaseHas('intelligence_evaluation_runs', [
            'engine' => 'hybrid',
            'case_count' => 1,
            'recall_at_k' => 1,
            'mean_reciprocal_rank' => 1,
            'status' => 'passed',
        ]);
        $this->assertDatabaseHas('intelligence_evaluation_results', ['rank' => 1, 'passed' => true]);
    }
}
