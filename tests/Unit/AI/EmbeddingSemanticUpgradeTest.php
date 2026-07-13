<?php

namespace Tests\Unit\AI;

use App\Contracts\EmbeddingsGateway;
use App\Domain\AI\Knowledge\EmbeddingIdentity;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeQueryEmbedding;
use App\Domain\AI\Knowledge\OpenAiCompatibleEmbeddingsGateway;
use App\Domain\AI\Knowledge\QueryEmbeddingBroker;
use App\Domain\AI\Knowledge\VectorMath;
use App\Domain\AI\Semantic\ArabicNormalizer;
use App\Domain\AI\Semantic\ConceptLexicon;
use App\Domain\AI\Semantic\EmbeddingSemanticMatcher;
use App\Domain\AI\Semantic\LexicalSemanticMatcher;
use App\Domain\AI\Semantic\SemanticMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmbeddingSemanticUpgradeTest extends TestCase
{
    use RefreshDatabase;

    /** بوابة تضمينات اصطناعية حتمية للاختبار: متجه ثابت لكل نص معروف. */
    private function fakeGateway(array $map): EmbeddingsGateway
    {
        return new class($map) implements EmbeddingsGateway
        {
            public function __construct(private readonly array $map) {}

            public function enabled(): bool
            {
                return true;
            }

            public function model(): string
            {
                return 'fake-embed';
            }

            public function embed(array $texts, string $inputType = 'passage'): ?array
            {
                $vectors = [];
                foreach ($texts as $text) {
                    $vectors[] = $this->map[trim($text)] ?? [0.001, 0.001, 0.001];
                }

                return $vectors;
            }
        };
    }

    #[Test]
    public function http_gateway_returns_ordered_vectors_and_degrades_without_key(): void
    {
        config([
            'services.knowledge.embedding_api.enabled' => true,
            'services.knowledge.embedding_api.key' => 'test-key',
        ]);
        Http::fake([
            '*/embeddings' => Http::response([
                'data' => [
                    ['index' => 1, 'embedding' => [0.4, 0.5]],
                    ['index' => 0, 'embedding' => [0.1, 0.2]],
                ],
            ]),
        ]);

        $gateway = new OpenAiCompatibleEmbeddingsGateway;
        $vectors = $gateway->embed(['أول', 'ثاني'], 'query');

        $this->assertSame([[0.1, 0.2], [0.4, 0.5]], $vectors);

        config(['services.knowledge.embedding_api.key' => null]);
        $this->assertFalse($gateway->enabled());
        $this->assertNull($gateway->embed(['نص']));
    }

    #[Test]
    public function embedding_matcher_understands_meaning_beyond_shared_words(): void
    {
        // نصان بلا أي كلمة مشتركة لكن متجهاهما متطابقان (نفس المعنى).
        $a = 'زيادة الحجوزات المؤكدة للعيادة';
        $b = 'رفع عدد المواعيد المدفوعة للمركز الطبي';
        $map = [$a => [1.0, 0.0, 0.0], $b => [1.0, 0.0, 0.0]];

        $lexical = new LexicalSemanticMatcher(new ArabicNormalizer, new ConceptLexicon);
        $matcher = new EmbeddingSemanticMatcher(
            $this->fakeGateway($map),
            $lexical,
            new VectorMath(2, 4096),
            new ConceptLexicon,
        );

        $this->assertGreaterThan(
            $lexical->similarity($a, $b),
            $matcher->similarity($a, $b),
        );
        $this->assertGreaterThanOrEqual(0.9, $matcher->similarity($a, $b));
    }

    #[Test]
    public function embedding_matcher_falls_back_to_lexical_when_gateway_disabled(): void
    {
        $disabled = new class implements EmbeddingsGateway
        {
            public function enabled(): bool
            {
                return false;
            }

            public function model(): string
            {
                return 'none';
            }

            public function embed(array $texts, string $inputType = 'passage'): ?array
            {
                return null;
            }
        };

        $lexical = new LexicalSemanticMatcher(new ArabicNormalizer, new ConceptLexicon);
        $matcher = new EmbeddingSemanticMatcher($disabled, $lexical, new VectorMath(2, 4096), new ConceptLexicon);

        $a = 'خطة تسويق للمطاعم';
        $b = 'خطة تسويق للمطاعم الجديدة';

        $this->assertSame($lexical->similarity($a, $b), $matcher->similarity($a, $b));
    }

    #[Test]
    public function query_broker_embeds_inline_and_caches_the_vector(): void
    {
        config([
            'services.knowledge.embedding_api.enabled' => true,
            'services.knowledge.embedding_api.key' => 'test-key',
            'services.knowledge.embedding_api.model' => 'fake-embed',
            'services.private_worker.enabled' => false,
        ]);

        $broker = new QueryEmbeddingBroker(
            $this->fakeGateway(['ما أفضل قناة تسويق؟' => [3.0, 4.0]]),
            new VectorMath(2, 4096),
        );

        $vector = $broker->findOrQueue(KnowledgeScope::global(), 'ما أفضل قناة تسويق؟');

        // متجه مُطبَّع (3,4)/5 = (0.6, 0.8) ومخزّن في كاش الاستعلامات.
        $this->assertNotNull($vector);
        $this->assertEqualsWithDelta(0.6, $vector[0], 0.0001);
        $this->assertEqualsWithDelta(0.8, $vector[1], 0.0001);
        $this->assertSame(1, KnowledgeQueryEmbedding::query()->count());

        // النداء الثاني يُخدم من الكاش (نفس المتجه دون صف جديد).
        $again = $broker->findOrQueue(KnowledgeScope::global(), 'ما أفضل قناة تسويق؟');
        $this->assertSame(1, KnowledgeQueryEmbedding::query()->count());
        $this->assertEqualsWithDelta($vector[0], $again[0], 0.0001);
    }

    #[Test]
    public function embedding_identity_switches_between_api_and_worker_models(): void
    {
        config([
            'services.knowledge.embedding_api.enabled' => true,
            'services.knowledge.embedding_api.key' => 'k',
            'services.knowledge.embedding_api.model' => 'baai/bge-m3',
            'services.knowledge.embedding_model' => 'nomic-embed-text',
            'services.private_worker.enabled' => false,
        ]);
        $this->assertSame('baai/bge-m3', EmbeddingIdentity::modelName());

        config(['services.private_worker.enabled' => true]);
        $this->assertSame('nomic-embed-text', EmbeddingIdentity::modelName());
    }

    #[Test]
    public function container_binds_the_embedding_matcher_as_the_semantic_layer(): void
    {
        $this->assertInstanceOf(EmbeddingSemanticMatcher::class, app(SemanticMatcher::class));
    }
}
