<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StructuredKnowledgeRepositoryTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as runFrameworkDatabaseMigrations;
    }

    private StructuredKnowledgeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new StructuredKnowledgeRepository;
    }

    public function runDatabaseMigrations(): void
    {
        $this->beforeApplicationDestroyed(function (): void {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA writable_schema = ON');

                return;
            }

            if (DB::getDriverName() !== 'mysql') {
                return;
            }

            $foreignKeys = DB::select(<<<'SQL'
                SELECT DISTINCT TABLE_NAME, COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                SQL);

            foreach ($foreignKeys as $foreignKey) {
                $indexName = 'test_fk_support_'.substr(hash('sha256', "{$foreignKey->TABLE_NAME}.{$foreignKey->COLUMN_NAME}"), 0, 16);

                DB::connection()->getSchemaBuilder()->table(
                    $foreignKey->TABLE_NAME,
                    fn (Blueprint $table) => $table->index([$foreignKey->COLUMN_NAME], $indexName)
                );
            }
        });

        $this->runFrameworkDatabaseMigrations();
    }

    #[Test]
    public function identical_content_is_stored_idempotently(): void
    {
        [, , , $scope] = $this->createTenant('A');
        $chunks = [['content' => "  فقرة\r\nواحدة  ", 'heading' => 'مقدمة', 'locator' => ['page' => 1]]];

        $first = $this->repository->storeDocument($scope, ' manual ', ' knowledge://guide ', 'Guide', "  محتوى\r\nالدليل  ", $chunks, 80);
        $second = $this->repository->storeDocument($scope, ' manual ', ' knowledge://guide ', 'Changed title', "  محتوى\r\nالدليل  ", $chunks, 10);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->assertDatabaseCount('knowledge_chunks', 1);
        $this->assertSame("محتوى\nالدليل", $first->content);
        $this->assertSame($scope->key(), $first->source->scope_key);
    }

    #[Test]
    public function changed_content_creates_the_next_version_and_latest_returns_it_with_chunks(): void
    {
        [, , , $scope] = $this->createTenant('Versions');

        $versionOne = $this->store($scope, 'الإصدار الأول', [['content' => 'قديم']]);
        $versionTwo = $this->store($scope, 'الإصدار الثاني', [['content' => 'حديث'], ['content' => 'تكملة']]);
        $latest = $this->repository->latestDocument($scope, 'manual', 'knowledge://guide');

        $this->assertSame(1, $versionOne->version);
        $this->assertSame(2, $versionTwo->version);
        $this->assertTrue($versionTwo->is($latest));
        $this->assertTrue($latest->relationLoaded('chunks'));
        $this->assertSame(['حديث', 'تكملة'], $latest->chunks->pluck('content')->all());
        $this->assertDatabaseCount('knowledge_documents', 2);
    }

    #[Test]
    public function latest_and_search_are_strictly_isolated_to_the_project_scope(): void
    {
        [$account, , , $scopeA] = $this->createTenant('A');
        [, , , $scopeB] = $this->createTenant('B', $account);
        $global = KnowledgeScope::global();

        $documentA = $this->store($scopeA, 'معرفة ألف', [['content' => 'projectalphauniqueterm ألف']]);
        $this->store($scopeB, 'معرفة باء', [['content' => 'projectbetauniqueterm باء']]);
        $this->store($global, 'معرفة عامة', [['content' => 'globaluniqueterm عامة']]);

        $this->assertTrue($documentA->is($this->repository->latestDocument($scopeA, 'manual', 'knowledge://guide')));
        $this->assertSame(['projectalphauniqueterm ألف'], $this->repository->searchText($scopeA, 'projectalphauniqueterm')->pluck('content')->all());
    }

    #[Test]
    public function a_corrupt_source_occupying_the_identity_is_rejected(): void
    {
        [$account, , $projectA, $scopeA] = $this->createTenant('Corrupt A');
        [, $workspaceB, $projectB] = $this->createTenant('Corrupt B', $account);
        $identityHash = $this->identityHash($scopeA, 'manual', 'knowledge://guide');

        KnowledgeSource::query()->create([
            'account_id' => $account->id,
            'workspace_id' => $workspaceB->id,
            'project_id' => $projectB->id,
            'scope_key' => $scopeA->key(),
            'kind' => 'manual',
            'canonical_uri' => 'knowledge://guide',
            'identity_hash' => $identityHash,
            'trust_score' => 50,
            'visibility' => 'project',
        ]);

        $this->expectException(LogicException::class);
        $this->store($scopeA, 'محتوى', [['content' => 'فقرة']]);
    }

    #[Test]
    public function invalid_api_inputs_are_rejected(): void
    {
        [, , , $scope] = $this->createTenant('Validation');

        foreach ([
            fn () => $this->repository->storeDocument($scope, 'manual', 'uri', 'T', 'C', [], -1),
            fn () => $this->repository->storeDocument($scope, 'manual', 'uri', 'T', 'C', [], 101),
            fn () => $this->repository->storeDocument($scope, ' ', 'uri', 'T', 'C', []),
            fn () => $this->repository->storeDocument($scope, 'manual', ' ', 'T', 'C', []),
            fn () => $this->repository->storeDocument($scope, 'manual', 'uri', 'T', 'C', [['content' => ' ']]),
            fn () => $this->repository->searchText($scope, 'query', 0),
            fn () => $this->repository->searchText($scope, 'query', 101),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected invalid input to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function sqlite_search_escapes_wildcards_and_backslashes_and_matches_arabic(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite literal search behavior requires the SQLite test connection.');
        }

        [, , , $scope] = $this->createTenant('Search');
        $this->store($scope, 'بحث', [
            ['content' => 'نسبة 100% مؤكدة'],
            ['content' => 'رمز file_name'],
            ['content' => 'مسار C:\\docs'],
            ['content' => 'نتيجة عربية طبيعية'],
            ['content' => 'نسبة 1000 مؤكدة ورمز filename ومسار C:docs'],
        ]);

        $this->assertSame(['نسبة 100% مؤكدة'], $this->repository->searchText($scope, '100%')->pluck('content')->all());
        $this->assertSame(['رمز file_name'], $this->repository->searchText($scope, 'file_name')->pluck('content')->all());
        $this->assertSame(['مسار C:\\docs'], $this->repository->searchText($scope, 'C:\\docs')->pluck('content')->all());
        $this->assertSame(['نتيجة عربية طبيعية'], $this->repository->searchText($scope, 'عربية')->pluck('content')->all());
        $this->assertCount(0, $this->repository->searchText($scope, '   '));
    }

    #[Test]
    public function chunks_preserve_array_order_and_have_deterministic_token_counts(): void
    {
        [, , , $scope] = $this->createTenant('Chunks');

        $document = $this->store($scope, 'ترتيب', [
            ['content' => ' أ '],
            ['content' => '12345', 'heading' => null, 'locator' => ['line' => 2]],
            ['content' => '123456789'],
        ]);

        $this->assertSame([0, 1, 2], $document->chunks()->orderBy('position')->pluck('position')->all());
        $this->assertSame([1, 2, 3], $document->chunks()->orderBy('position')->pluck('token_count')->all());
        $this->assertSame(['أ', '12345', '123456789'], $document->chunks()->orderBy('position')->pluck('content')->all());
    }

    #[Test]
    public function invalid_chunk_rolls_back_the_source_and_document_transaction(): void
    {
        [, , , $scope] = $this->createTenant('Rollback');

        try {
            $this->store($scope, 'محتوى', [['content' => 'صالح'], ['content' => " \r\n "]]);
            $this->fail('Expected invalid chunk to be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('knowledge_sources', 0);
            $this->assertDatabaseCount('knowledge_documents', 0);
            $this->assertDatabaseCount('knowledge_chunks', 0);
        }
    }

    private function store(KnowledgeScope $scope, string $content, array $chunks): KnowledgeDocument
    {
        return $this->repository->storeDocument($scope, 'manual', 'knowledge://guide', 'Guide', $content, $chunks, 75);
    }

    private function createTenant(string $suffix, ?Account $account = null): array
    {
        if ($account === null) {
            $user = User::factory()->create();
            $account = Account::query()->create([
                'owner_user_id' => $user->id,
                'name' => "Account {$suffix}",
                'billing_email' => $user->email,
                'status' => 'active',
            ]);
        }

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => "Workspace {$suffix}",
            'type' => 'team',
            'status' => 'active',
        ]);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => "Project {$suffix}",
            'stage' => 1,
            'status' => 'active',
        ]);

        return [$account, $workspace, $project, KnowledgeScope::fromProject($project)];
    }

    private function identityHash(KnowledgeScope $scope, string $kind, string $canonicalUri): string
    {
        return hash('sha256', implode('|', [
            $scope->visibility,
            $scope->accountId ?? 'global',
            $scope->workspaceId ?? 'global',
            $scope->projectId ?? 'global',
            trim($kind),
            trim($canonicalUri),
        ]));
    }
}
