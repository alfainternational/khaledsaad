<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
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
        $this->assertSame(500, config('services.knowledge.lock_wait_milliseconds'));
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

        $store->remember('playbook.stable', ['nested' => ['b' => 2, 'a' => 1], 'value' => false]);
        $store->remember('playbook.stable', ['value' => false, 'nested' => ['a' => 1, 'b' => 2]]);

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
            ['key_hash' => hash('sha256', 'playbook.resilient'), 'exception' => RuntimeException::class],
        );

        (new KnowledgeStore($repository))->remember('playbook.resilient', [
            'secret' => 'لا تكشفني',
            'answer' => 42,
        ]);

        $memory = (new KnowledgeStore)->recall('playbook.resilient');
        $this->assertSame(42, $memory['data']['answer']);
        $this->assertDatabaseCount('knowledge_documents', 0);
    }

    #[Test]
    public function unscoped_and_tenant_tainted_global_memories_are_not_mirrored(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        Log::shouldReceive('notice')->twice()->with(
            'Structured knowledge mirror skipped.',
            Mockery::on(fn (array $context): bool => isset($context['key_hash'], $context['reason'])
                && ! array_key_exists('key', $context)),
        );

        $store = new KnowledgeStore(new StructuredKnowledgeRepository);
        $store->remember('web.market-query', ['query' => 'خطة عميل خاصة']);
        $store->remember('playbook.tainted', ['workspace_id' => 77, 'principle' => 'خاص']);

        $this->assertDatabaseCount('knowledge_sources', 0);
        Storage::disk('local')->assertExists('ai-knowledge/web.market-query.json');
        Storage::disk('local')->assertExists('ai-knowledge/playbook.tainted.json');
    }

    #[Test]
    public function tenant_memories_are_mirrored_only_to_the_validated_workspace_or_project_scope(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        [$accountA, $workspaceA, $projectA] = $this->createTenant('A');
        [, $workspaceB] = $this->createTenant('B');
        $store = new KnowledgeStore(new StructuredKnowledgeRepository);

        $store->remember('monitor.performance.ws'.$workspaceA->id, [
            'workspace_id' => $workspaceA->id,
            'series' => [1, 2, 3, 8],
        ]);
        $store->remember('agent.strategist.ws'.$workspaceA->id.'.audit', [
            'workspace_id' => $workspaceA->id,
            'project_id' => $projectA->id,
            'headline' => 'نتيجة خاصة',
        ]);
        Log::shouldReceive('notice')->once()->with(
            'Structured knowledge mirror skipped.',
            ['key_hash' => hash('sha256', 'monitor.performance.ws'.$workspaceB->id), 'reason' => 'scope_unresolved'],
        );
        $store->remember('monitor.performance.ws'.$workspaceB->id, [
            'workspace_id' => $workspaceA->id,
            'series' => [9],
        ]);

        $workspaceScope = KnowledgeScope::forWorkspace($accountA->id, $workspaceA->id);
        $projectScope = KnowledgeScope::forProject($accountA->id, $workspaceA->id, $projectA->id);
        $repository = new StructuredKnowledgeRepository;
        $this->assertNotNull($repository->latestDocument(
            $workspaceScope,
            'legacy_memory',
            'legacy://monitor.performance.ws'.$workspaceA->id,
        ));
        $this->assertNotNull($repository->latestDocument(
            $projectScope,
            'legacy_memory',
            'legacy://agent.strategist.ws'.$workspaceA->id.'.audit',
        ));
        $this->assertDatabaseCount('knowledge_sources', 2);

        $store->forget('agent.strategist.ws'.$workspaceA->id.'.audit');

        $this->assertNull($repository->latestDocument(
            $projectScope,
            'legacy_memory',
            'legacy://agent.strategist.ws'.$workspaceA->id.'.audit',
        ));
        $this->assertNotNull($repository->latestDocument(
            $workspaceScope,
            'legacy_memory',
            'legacy://monitor.performance.ws'.$workspaceA->id,
        ));
    }

    #[Test]
    public function a_false_legacy_write_never_starts_the_structured_mirror(): void
    {
        $this->enableDualWrite();
        $key = 'playbook.storage-failure';
        $lockPath = storage_path('framework/testing/'.hash('sha256', $key).'.lock');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('path')->once()->andReturn($lockPath);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Log::shouldReceive('warning')->once()->with(
            'Legacy knowledge write failed.',
            ['key_hash' => hash('sha256', $key), 'reason' => 'storage_write_failed'],
        );
        $repository = new class extends StructuredKnowledgeRepository
        {
            public function storeDocument(
                KnowledgeScope $scope,
                string $kind,
                string $canonicalUri,
                string $title,
                string $content,
                array $chunks,
                int $trustScore = 50,
            ): KnowledgeDocument {
                throw new RuntimeException('Structured mirror must not start.');
            }
        };

        (new KnowledgeStore($repository))->remember($key, ['value' => 1]);

        $this->assertDatabaseCount('knowledge_documents', 0);
    }

    #[Test]
    public function successful_legacy_delete_deactivates_only_the_matching_structured_document(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $store = new KnowledgeStore(new StructuredKnowledgeRepository);
        $store->remember('playbook.delete-me', ['value' => 'remove']);
        $store->remember('playbook.keep-me', ['value' => 'keep']);

        $store->forget('playbook.delete-me');

        Storage::disk('local')->assertMissing('ai-knowledge/playbook.delete-me.json');
        $repository = new StructuredKnowledgeRepository;
        $this->assertNull($repository->latestDocument(KnowledgeScope::global(), 'legacy_memory', 'legacy://playbook.delete-me'));
        $this->assertNotNull($repository->latestDocument(KnowledgeScope::global(), 'legacy_memory', 'legacy://playbook.keep-me'));
    }

    #[Test]
    public function structured_delete_failure_does_not_restore_legacy_data_and_logs_only_a_key_hash(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $key = 'playbook.delete-failure';
        $repository = new class extends StructuredKnowledgeRepository
        {
            public function deactivateDocuments(KnowledgeScope $scope, string $kind, string $canonicalUri): int
            {
                throw new RuntimeException('private database detail');
            }
        };
        $store = new KnowledgeStore($repository);
        config()->set('services.knowledge.dual_write', false);
        $store->remember($key, ['value' => 1]);
        config()->set('services.knowledge.dual_write', true);
        Log::shouldReceive('warning')->once()->with(
            'Structured knowledge delete failed.',
            ['key_hash' => hash('sha256', $key), 'exception' => RuntimeException::class],
        );

        $store->forget($key);

        Storage::disk('local')->assertMissing('ai-knowledge/'.$key.'.json');
    }

    #[Test]
    public function ambiguous_new_keys_are_rejected_without_overwriting_legacy_files(): void
    {
        Storage::fake('local');
        $store = new KnowledgeStore;

        foreach (['a/b', 'a?b', ' spaced ', "line\nbreak", 'UPPER.case'] as $key) {
            try {
                $store->remember($key, ['value' => 1]);
                $this->fail('Expected an ambiguous key to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], Storage::disk('local')->files('ai-knowledge'));
    }

    #[Test]
    public function legacy_sanitized_filenames_remain_readable_and_deletable(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-knowledge/a_b.json', json_encode([
            'key' => 'a/b',
            'data' => ['value' => 'legacy'],
            'learned_at' => now()->toIso8601String(),
        ]));
        $store = new KnowledgeStore;

        $this->assertSame('legacy', $store->recall('a/b')['data']['value']);
        $store->forget('a/b');

        Storage::disk('local')->assertMissing('ai-knowledge/a_b.json');
    }

    #[Test]
    public function the_same_canonical_identity_uses_one_lock_file_and_releases_it_after_failure(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $key = 'playbook.locked';
        $repository = new class($key) extends StructuredKnowledgeRepository
        {
            public bool $lockWasHeldDuringMirror = false;

            public function __construct(private readonly string $key) {}

            public function storeDocument(
                KnowledgeScope $scope,
                string $kind,
                string $canonicalUri,
                string $title,
                string $content,
                array $chunks,
                int $trustScore = 50,
            ): KnowledgeDocument {
                $path = Storage::disk('local')->path('ai-knowledge/.locks/'.hash('sha256', $this->key).'.lock');
                $handle = fopen($path, 'c+');
                $this->lockWasHeldDuringMirror = is_resource($handle) && ! flock($handle, LOCK_EX | LOCK_NB);

                if (is_resource($handle)) {
                    fclose($handle);
                }

                throw new RuntimeException('expected mirror failure');
            }
        };
        Log::shouldReceive('warning')->once();
        $store = new KnowledgeStore($repository);

        $store->remember($key, ['value' => 1]);

        $lockPath = Storage::disk('local')->path('ai-knowledge/.locks/'.hash('sha256', $key).'.lock');
        $this->assertTrue($repository->lockWasHeldDuringMirror);
        $this->assertFileExists($lockPath);
        $handle = fopen($lockPath, 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        flock($handle, LOCK_UN);
        fclose($handle);

        config()->set('services.knowledge.dual_write', false);
        $store->forget($key);
        $this->assertCount(1, glob(dirname($lockPath).'/*.lock') ?: []);
    }

    private function enableDualWrite(): void
    {
        config()->set('services.knowledge.structured_store', true);
        config()->set('services.knowledge.dual_write', true);
    }

    /**
     * @return array{Account, Workspace, Project}
     */
    private function createTenant(string $suffix): array
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Account '.$suffix,
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Workspace '.$suffix,
            'type' => 'team',
            'status' => 'active',
        ]);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client '.$suffix,
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Project '.$suffix,
            'stage' => 1,
            'status' => 'active',
        ]);

        return [$account, $workspace, $project];
    }
}
