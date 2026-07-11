<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
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
            'enabled: 1',
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
}
