<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ImportLegacyKnowledgeCommandTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function it_imports_legacy_json_deterministically_and_is_idempotent(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-knowledge/playbook.offer.json', json_encode([
            'key' => 'playbook.offer',
            'data' => [
                'quick_win' => 'أضف الدليل بجانب الدعوة للإجراء',
                'principles' => ['اربط الوعد بدليل', 'اجعل النتيجة قابلة للقياس'],
                'enabled' => true,
                'optional' => null,
            ],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ], JSON_UNESCAPED_UNICODE));

        $this->artisan('knowledge:import-legacy')
            ->expectsOutput('Imported: 1; unchanged: 0; skipped: 0')
            ->assertSuccessful();
        $this->artisan('knowledge:import-legacy')
            ->expectsOutput('Imported: 0; unchanged: 1; skipped: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->assertDatabaseCount('knowledge_chunks', 1);
        $this->assertDatabaseHas('knowledge_sources', [
            'kind' => 'legacy_memory',
            'canonical_uri' => 'legacy://playbook.offer',
            'trust_score' => 50,
            'visibility' => 'global',
        ]);

        $document = KnowledgeDocument::query()->with('chunks')->sole();
        $expected = implode("\n", [
            'enabled: true',
            'optional: null',
            'principles.0: اربط الوعد بدليل',
            'principles.1: اجعل النتيجة قابلة للقياس',
            'quick_win: أضف الدليل بجانب الدعوة للإجراء',
        ]);

        $this->assertSame($expected, $document->content);
        $this->assertSame($expected, $document->chunks->sole()->content);
        $this->assertSame(0, $document->chunks->sole()->position);
    }

    #[Test]
    public function it_skips_malformed_and_invalid_files_without_aborting_valid_imports(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-knowledge/broken.json', '{not-json');
        Storage::disk('local')->put('ai-knowledge/missing-key.json', json_encode([
            'data' => ['value' => 'ignored'],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ]));
        Storage::disk('local')->put('ai-knowledge/missing-data.json', json_encode([
            'key' => 'missing.data',
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ]));
        Storage::disk('local')->put('ai-knowledge/missing-date.json', json_encode([
            'key' => 'missing.date',
            'data' => ['value' => 'ignored'],
        ]));
        Storage::disk('local')->put('ai-knowledge/non-scalar.json', json_encode([
            'key' => 'empty.content',
            'data' => ['object' => new \stdClass],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ]));
        Storage::disk('local')->put('ai-knowledge/valid.json', json_encode([
            'key' => 'valid.memory',
            'data' => ['answer' => 42],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ]));

        $this->artisan('knowledge:import-legacy')
            ->expectsOutput('Imported: 1; unchanged: 0; skipped: 5')
            ->assertSuccessful();

        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->assertDatabaseCount('knowledge_chunks', 1);
    }

    #[Test]
    public function equivalent_object_key_orders_produce_unchanged_content(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->put('ai-knowledge/memory.json', json_encode([
            'key' => 'ordered.memory',
            'data' => ['z' => 2, 'nested' => ['b' => 2, 'a' => 1], 'a' => 1],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ]));
        $this->artisan('knowledge:import-legacy')->assertSuccessful();

        $disk->put('ai-knowledge/memory.json', json_encode([
            'learned_at' => '2026-07-01T03:00:00+03:00',
            'data' => ['a' => 1, 'nested' => ['a' => 1, 'b' => 2], 'z' => 2],
            'key' => 'ordered.memory',
        ]));

        $this->artisan('knowledge:import-legacy')
            ->expectsOutput('Imported: 0; unchanged: 1; skipped: 0')
            ->assertSuccessful();
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->assertSame("a: 1\nnested.a: 1\nnested.b: 2\nz: 2", KnowledgeDocument::query()->sole()->content);
    }

    #[Test]
    public function it_preserves_list_order_distinguishes_boolean_values_and_escapes_unsafe_key_segments(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-knowledge/edge-cases.json', json_encode([
            'key' => 'edge.cases',
            'data' => [
                'items' => range(0, 12),
                'false_value' => false,
                'empty_value' => '',
                'a.b' => 'dotted',
                "line\nkey" => 'controlled',
                '~u000A' => 'literal escape marker',
            ],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ], JSON_UNESCAPED_UNICODE));

        $this->artisan('knowledge:import-legacy')->assertSuccessful();

        $lines = explode("\n", KnowledgeDocument::query()->sole()->content);
        $this->assertSame([
            'a~1b: dotted',
            'empty_value: ',
            'false_value: false',
            'items.0: 0',
            'items.1: 1',
            'items.2: 2',
            'items.3: 3',
            'items.4: 4',
            'items.5: 5',
            'items.6: 6',
            'items.7: 7',
            'items.8: 8',
            'items.9: 9',
            'items.10: 10',
            'items.11: 11',
            'items.12: 12',
            'line~u000Akey: controlled',
            '~0u000A: literal escape marker',
        ], $lines);
    }

    #[Test]
    public function renamed_and_duplicate_files_with_the_same_key_and_content_are_unchanged(): void
    {
        Storage::fake('local');
        $payload = json_encode([
            'key' => 'stable.memory',
            'data' => ['answer' => 42],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ]);
        $disk = Storage::disk('local');
        $disk->put('ai-knowledge/original.json', $payload);

        $this->artisan('knowledge:import-legacy')
            ->expectsOutput('Imported: 1; unchanged: 0; skipped: 0')
            ->assertSuccessful();

        $disk->delete('ai-knowledge/original.json');
        $disk->put('ai-knowledge/renamed.json', $payload);
        $disk->put('ai-knowledge/second-copy.json', $payload);

        $this->artisan('knowledge:import-legacy')
            ->expectsOutput('Imported: 0; unchanged: 2; skipped: 0')
            ->assertSuccessful();
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->assertSame(
            ['canonical_uri' => 'legacy://stable.memory'],
            KnowledgeDocument::query()->with('chunks')->sole()->chunks->sole()->locator_json,
        );
    }

    #[Test]
    public function learned_at_must_use_the_legacy_store_iso_8601_format(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-knowledge/invalid-date.json', json_encode([
            'key' => 'invalid.date',
            'data' => ['answer' => 42],
            'learned_at' => 'next Tuesday',
        ]));

        $this->artisan('knowledge:import-legacy')
            ->expectsOutput('Imported: 0; unchanged: 0; skipped: 1')
            ->assertSuccessful();
        $this->assertDatabaseCount('knowledge_documents', 0);
    }

    #[Test]
    public function unexpected_persistence_failures_return_a_non_zero_exit_code(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-knowledge/valid.json', json_encode([
            'key' => 'valid.memory',
            'data' => ['answer' => 42],
            'learned_at' => '2026-07-01T03:00:00+03:00',
        ]));

        $this->app->instance(StructuredKnowledgeRepository::class, new class extends StructuredKnowledgeRepository
        {
            public function latestDocument(KnowledgeScope $scope, string $kind, string $canonicalUri): ?KnowledgeDocument
            {
                throw new RuntimeException('Persistence unavailable.');
            }
        });

        $this->artisan('knowledge:import-legacy')->assertFailed();
        $this->assertDatabaseCount('knowledge_documents', 0);
    }
}
