<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class KnowledgeStoreCompatibilityTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function rollout_flags_are_disabled_by_default(): void
    {
        $this->assertFalse(config('services.knowledge.structured_store'));
        $this->assertFalse(config('services.knowledge.dual_write'));
        $this->assertFalse(config('services.knowledge.project_sync'));
    }

    #[Test]
    public function disabled_dual_write_preserves_json_behavior_without_structured_rows(): void
    {
        Storage::fake('local');
        config()->set('services.knowledge.structured_store', true);
        config()->set('services.knowledge.dual_write', false);

        $this->app->make(KnowledgeStore::class)->remember('playbook.offer', [
            'principle' => 'اربط الوعد بالدليل',
        ]);

        $memory = (new KnowledgeStore)->recall('playbook.offer');
        $this->assertSame('playbook.offer', $memory['key']);
        $this->assertSame(['principle' => 'اربط الوعد بالدليل'], $memory['data']);
        $this->assertNotEmpty($memory['learned_at']);
        $this->assertDatabaseCount('knowledge_sources', 0);
        $this->assertDatabaseCount('knowledge_documents', 0);
        $this->assertDatabaseCount('knowledge_chunks', 0);
    }

    #[Test]
    public function both_flags_are_required_to_enable_structured_writes(): void
    {
        Storage::fake('local');
        config()->set('services.knowledge.structured_store', false);
        config()->set('services.knowledge.dual_write', true);

        (new KnowledgeStore(new StructuredKnowledgeRepository))->remember('disabled.structured', ['value' => 1]);

        Storage::disk('local')->assertExists('ai-knowledge/disabled.structured.json');
        $this->assertDatabaseCount('knowledge_sources', 0);
    }

    #[Test]
    public function enabled_dual_write_mirrors_canonical_arabic_and_nested_data(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();

        $this->app->make(KnowledgeStore::class)->remember('playbook.offer', [
            'quick_win' => 'أضف الدليل بجانب الدعوة للإجراء',
            'principles' => ['اربط الوعد بدليل', 'اجعل النتيجة قابلة للقياس'],
            'settings' => ['enabled' => true, 'optional' => null],
        ]);

        $memory = (new KnowledgeStore)->recall('playbook.offer');
        $this->assertSame('أضف الدليل بجانب الدعوة للإجراء', $memory['data']['quick_win']);
        $this->assertDatabaseHas('knowledge_sources', [
            'kind' => 'legacy_memory',
            'canonical_uri' => 'legacy://playbook.offer',
            'trust_score' => 50,
            'visibility' => 'global',
        ]);

        $expected = implode("\n", [
            'principles.0: "اربط الوعد بدليل"',
            'principles.1: "اجعل النتيجة قابلة للقياس"',
            'quick_win: "أضف الدليل بجانب الدعوة للإجراء"',
            'settings.enabled: true',
            'settings.optional: null',
        ]);
        $document = KnowledgeDocument::query()->with('chunks')->sole();
        $this->assertSame($expected, $document->content);
        $this->assertSame($expected, $document->chunks->sole()->content);
        $this->assertSame(['canonical_uri' => 'legacy://playbook.offer'], $document->chunks->sole()->locator_json);
    }

    #[Test]
    public function repeated_equivalent_writes_are_idempotent(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $store = new KnowledgeStore(new StructuredKnowledgeRepository);

        $store->remember('stable.memory', ['nested' => ['b' => 2, 'a' => 1], 'value' => false]);
        $store->remember('stable.memory', ['value' => false, 'nested' => ['a' => 1, 'b' => 2]]);

        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->assertDatabaseCount('knowledge_chunks', 1);
        $this->assertSame("nested.a: 1\nnested.b: 2\nvalue: false", KnowledgeDocument::query()->sole()->content);
    }

    #[Test]
    public function structured_failure_keeps_legacy_json_and_logs_only_safe_context(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $failure = new RuntimeException('Database password and secret payload: لا تكشفني');
        $repository = new class($failure) extends StructuredKnowledgeRepository
        {
            public function __construct(private readonly RuntimeException $failure) {}

            public function storeDocument(
                KnowledgeScope $scope,
                string $kind,
                string $canonicalUri,
                string $title,
                string $content,
                array $chunks,
                int $trustScore = 50,
            ): KnowledgeDocument {
                throw $this->failure;
            }
        };
        Log::shouldReceive('warning')->once()->with(
            'Structured knowledge dual write failed.',
            ['key' => 'resilient.memory', 'exception' => RuntimeException::class],
        );

        (new KnowledgeStore($repository))->remember('resilient.memory', [
            'secret' => 'لا تكشفني',
            'answer' => 42,
        ]);

        $memory = (new KnowledgeStore)->recall('resilient.memory');
        $this->assertSame(42, $memory['data']['answer']);
        $this->assertDatabaseCount('knowledge_documents', 0);
    }

    private function enableDualWrite(): void
    {
        config()->set('services.knowledge.structured_store', true);
        config()->set('services.knowledge.dual_write', true);
    }
}
