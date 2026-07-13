<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->assertSame([], config('services.knowledge.mapping_previous_keys'));
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
            'canonical_uri' => $this->structuredUri('playbook.offer'),
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
        $this->assertSame([
            'canonical_uri' => $this->structuredUri('playbook.offer'),
            'key_hash' => hash('sha256', 'playbook.offer'),
        ], $document->chunks->sole()->locator_json);
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

            public function storePendingDocument(
                KnowledgeScope $scope,
                string $kind,
                string $canonicalUri,
                string $title,
                string $content,
                array $chunks,
                string $generation,
                int $trustScore = 50,
            ): ?KnowledgeDocument {
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
        Storage::disk('local')->assertExists($this->mappingPath('playbook.resilient'));
        Storage::disk('local')->delete($this->legacyPath('playbook.resilient'));
        (new KnowledgeStore(new StructuredKnowledgeRepository))->forget('playbook.resilient');
        Storage::disk('local')->assertMissing($this->mappingPath('playbook.resilient'));
        $this->assertSame(0, KnowledgeDocument::query()->where('status', 'active')->count());
    }

    #[Test]
    public function public_web_research_is_global_while_tenant_tainted_memories_are_not_mirrored(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        Log::shouldReceive('notice')->once()->with(
            'Structured knowledge mirror skipped.',
            Mockery::on(fn (array $context): bool => isset($context['key_hash'], $context['reason'])
                && ! array_key_exists('key', $context)),
        );

        $store = new KnowledgeStore(new StructuredKnowledgeRepository);
        $store->remember('web.market-query', ['query' => 'خطة عميل خاصة']);
        $store->remember('playbook.tainted', ['workspace_id' => 77, 'principle' => 'خاص']);

        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseHas('knowledge_sources', ['visibility' => 'global']);
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
            $this->structuredUri('monitor.performance.ws'.$workspaceA->id),
        ));
        $this->assertNotNull($repository->latestDocument(
            $projectScope,
            'legacy_memory',
            $this->structuredUri('agent.strategist.ws'.$workspaceA->id.'.audit'),
        ));
        $this->assertDatabaseCount('knowledge_sources', 2);

        $store->forget('agent.strategist.ws'.$workspaceA->id.'.audit');

        $this->assertNull($repository->latestDocument(
            $projectScope,
            'legacy_memory',
            $this->structuredUri('agent.strategist.ws'.$workspaceA->id.'.audit'),
        ));
        $this->assertNotNull($repository->latestDocument(
            $workspaceScope,
            'legacy_memory',
            $this->structuredUri('monitor.performance.ws'.$workspaceA->id),
        ));
    }

    #[Test]
    public function a_false_legacy_write_never_starts_the_structured_mirror(): void
    {
        $this->enableDualWrite();
        $key = 'playbook.storage-failure';
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Log::shouldReceive('warning')->once()->with(
            'Legacy knowledge write failed.',
            ['key_hash' => hash('sha256', $key), 'reason' => 'storage_write_failed'],
        );
        $repository = new class extends StructuredKnowledgeRepository
        {
            public function storePendingDocument(
                KnowledgeScope $scope,
                string $kind,
                string $canonicalUri,
                string $title,
                string $content,
                array $chunks,
                string $generation,
                int $trustScore = 50,
            ): ?KnowledgeDocument {
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
        $this->assertNull($repository->latestDocument(
            KnowledgeScope::global(),
            'legacy_memory',
            $this->structuredUri('playbook.delete-me'),
        ));
        $this->assertNotNull($repository->latestDocument(
            KnowledgeScope::global(),
            'legacy_memory',
            $this->structuredUri('playbook.keep-me'),
        ));
    }

    #[Test]
    public function structured_delete_failure_does_not_restore_legacy_data_and_logs_only_a_key_hash(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $key = 'playbook.delete-failure';
        (new KnowledgeStore(new StructuredKnowledgeRepository))->remember($key, ['value' => 1]);
        Storage::disk('local')->assertExists($this->mappingPath($key));
        $repository = new class extends StructuredKnowledgeRepository
        {
            public function deactivateDocuments(KnowledgeScope $scope, string $kind, string $canonicalUri): int
            {
                throw new RuntimeException('private database detail');
            }
        };
        $store = new KnowledgeStore($repository);
        Log::shouldReceive('warning')->once()->with(
            'Structured knowledge delete failed.',
            ['key_hash' => hash('sha256', $key), 'exception' => RuntimeException::class],
        );

        $store->forget($key);

        Storage::disk('local')->assertMissing('ai-knowledge/'.$key.'.json');
        Storage::disk('local')->assertExists($this->mappingPath($key));
        $this->assertNotNull((new StructuredKnowledgeRepository)->latestDocument(
            KnowledgeScope::global(),
            'legacy_memory',
            $this->structuredUri($key),
        ));
    }

    #[Test]
    public function arbitrary_legacy_keys_keep_exact_remember_recall_and_forget_behavior_with_rollout_off_and_on(): void
    {
        Storage::fake('local');
        $store = new KnowledgeStore;
        $keys = [
            'UPPER.case',
            'ذاكرة عربية',
            ' spaced key ',
            str_repeat('l', 210),
            'web.'.Str::slug('خطة تسويق', '_', 'ar'),
        ];

        foreach ([false, true] as $enabled) {
            config()->set('services.knowledge.structured_store', $enabled);
            config()->set('services.knowledge.dual_write', $enabled);

            if ($enabled) {
                Log::shouldReceive('notice')->times(count($keys) - 1)->with(
                    'Structured knowledge mirror skipped.',
                    Mockery::on(fn (array $context): bool => isset($context['key_hash'], $context['reason'])
                        && ! array_key_exists('key', $context)),
                );
            }

            foreach ($keys as $key) {
                $store->remember($key, ['value' => $key]);
                $this->assertSame($key, $store->recall($key)['key']);
                $this->assertSame($key, $store->recall($key)['data']['value']);
                $store->forget($key);
                $this->assertNull($store->recall($key));
            }
        }

        config()->set('services.knowledge.structured_store', false);
        config()->set('services.knowledge.dual_write', false);
        $store->remember('a/b', ['value' => 'slash']);
        $store->remember('a?b', ['value' => 'question']);
        $this->assertSame('question', $store->recall('a/b')['data']['value']);
        $this->assertSame('question', $store->recall('a?b')['data']['value']);
        $this->assertDatabaseCount('knowledge_sources', 1);
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
        $legacyPath = $this->legacyPath($key);
        $pathHash = hash('sha256', $legacyPath);
        $repository = new class($pathHash) extends StructuredKnowledgeRepository
        {
            public bool $lockWasHeldDuringMirror = false;

            public function __construct(private readonly string $pathHash) {}

            public function storePendingDocument(
                KnowledgeScope $scope,
                string $kind,
                string $canonicalUri,
                string $title,
                string $content,
                array $chunks,
                string $generation,
                int $trustScore = 50,
            ): ?KnowledgeDocument {
                $path = Storage::disk('local')->path('ai-knowledge/.locks/'.$this->pathHash.'.lock');
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

        $lockPath = Storage::disk('local')->path('ai-knowledge/.locks/'.$pathHash.'.lock');
        $this->assertTrue($repository->lockWasHeldDuringMirror);
        $this->assertFileExists($lockPath);
        $handle = fopen($lockPath, 'c+');
        $this->assertIsResource($handle);

        try {
            $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        config()->set('services.knowledge.dual_write', false);
        $store->forget($key);
        $this->assertCount(1, glob(dirname($lockPath).'/*.lock') ?: []);
    }

    #[Test]
    public function rollout_off_never_attempts_a_mirror_lock(): void
    {
        Storage::fake('local');
        $key = 'UPPER key';
        $lockPath = $this->lockPath($key);
        File::ensureDirectoryExists($lockPath);

        $store = new KnowledgeStore;
        $store->remember($key, ['value' => 'legacy']);

        $this->assertSame('legacy', $store->recall($key)['data']['value']);
        $store->forget($key);
        $this->assertNull($store->recall($key));
    }

    #[Test]
    public function rollout_on_lock_contention_keeps_json_and_records_a_pending_generation_without_active_content(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        config()->set('services.knowledge.lock_wait_milliseconds', 50);
        $key = 'playbook.contended';
        $lockPath = $this->lockPath($key);
        File::ensureDirectoryExists(dirname($lockPath));
        $handle = fopen($lockPath, 'c+');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        Log::shouldReceive('warning')->once()->with(
            'Knowledge mirror lock timed out.',
            ['key_hash' => hash('sha256', $key), 'reason' => 'lock_timeout'],
        );

        $store = new KnowledgeStore(new StructuredKnowledgeRepository);

        try {
            $store->remember($key, ['value' => 'authoritative']);

            $this->assertSame('authoritative', (new KnowledgeStore)->recall($key)['data']['value']);
            $this->assertDatabaseCount('knowledge_sources', 1);
            $this->assertDatabaseCount('knowledge_documents', 0);
            $this->assertSame(
                hash('sha256', Storage::disk('local')->get($this->legacyPath($key))),
                KnowledgeSource::query()->sole()->meta_json['legacy_pending_generation'],
            );
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertTrue($store->reconcile($key));
        $this->assertSame('value: "authoritative"', KnowledgeDocument::query()->where('status', 'active')->sole()->content);
        $this->assertArrayNotHasKey(
            'legacy_pending_generation',
            KnowledgeSource::query()->sole()->fresh()->meta_json,
        );
    }

    #[Test]
    public function mirror_lock_open_failure_keeps_json_and_does_not_escape_to_the_caller(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $key = 'playbook.lock-open';
        $lockPath = $this->lockPath($key);
        File::ensureDirectoryExists($lockPath);
        Log::shouldReceive('warning')->once()->with(
            'Knowledge mirror lock unavailable.',
            ['key_hash' => hash('sha256', $key), 'reason' => 'lock_open_failed'],
        );

        (new KnowledgeStore(new StructuredKnowledgeRepository))->remember($key, ['value' => 'authoritative']);

        $this->assertSame('authoritative', (new KnowledgeStore)->recall($key)['data']['value']);
        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_documents', 0);
        $this->assertSame(
            hash('sha256', Storage::disk('local')->get($this->legacyPath($key))),
            KnowledgeSource::query()->sole()->meta_json['legacy_pending_generation'],
        );
    }

    #[Test]
    public function mapping_false_or_exception_skips_store_and_leaves_no_orphan_after_missing_json_forget(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();

        foreach (['false', 'exception'] as $failure) {
            $key = 'playbook.mapping-'.$failure;
            $store = new class(new StructuredKnowledgeRepository, $failure) extends KnowledgeStore
            {
                public function __construct(StructuredKnowledgeRepository $repository, private readonly string $failure)
                {
                    parent::__construct($repository);
                }

                protected function writeMapping(
                    FilesystemAdapter $disk,
                    string $key,
                    KnowledgeScope $scope,
                    string $canonicalUri,
                ): bool {
                    if ($this->failure === 'exception') {
                        throw new RuntimeException('simulated map failure');
                    }

                    return false;
                }
            };
            Log::shouldReceive('warning')->once()->with(
                'Structured knowledge scope mapping write failed.',
                ['key_hash' => hash('sha256', $key), 'reason' => 'mapping_write_failed'],
            );

            $store->remember($key, ['value' => 'authoritative']);

            $this->assertSame('authoritative', $store->recall($key)['data']['value']);
            $this->assertDatabaseCount('knowledge_sources', 0);
            Storage::disk('local')->delete($this->legacyPath($key));
            $store->forget($key);
            $this->assertDatabaseCount('knowledge_sources', 0);
            $this->assertSame(0, KnowledgeDocument::query()->where('status', 'active')->count());
        }
    }

    #[Test]
    public function durable_scope_mapping_supports_missing_corrupt_and_repeated_tenant_deletes_without_cross_tenant_effects(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        [$accountA, $workspaceA, $projectA] = $this->createTenant('Delete A');
        [$accountB, $workspaceB, $projectB] = $this->createTenant('Delete B');
        $store = new KnowledgeStore(new StructuredKnowledgeRepository);
        $keyA = 'agent.strategist.ws'.$workspaceA->id.'.durable';
        $keyB = 'agent.strategist.ws'.$workspaceB->id.'.durable';

        foreach ([[$keyA, $workspaceA, $projectA], [$keyB, $workspaceB, $projectB]] as [$key, $workspace, $project]) {
            $store->remember($key, [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'headline' => 'private',
            ]);
            $mapping = json_decode(Storage::disk('local')->get($this->mappingPath($key)), true);
            $this->assertSame([
                'version', 'key_hash', 'path_hash', 'canonical_uri', 'visibility', 'account_id', 'workspace_id', 'project_id',
                'signing_key_id', 'signature',
            ], array_keys($mapping));
        }

        Storage::disk('local')->delete($this->legacyPath($keyA));
        Storage::disk('local')->put($this->legacyPath($keyB), '{corrupt-json');

        $store->forget($keyA);
        $store->forget($keyA);
        $this->assertNull((new StructuredKnowledgeRepository)->latestDocument(
            KnowledgeScope::forProject($accountA->id, $workspaceA->id, $projectA->id),
            'legacy_memory',
            $this->structuredUri($keyA),
        ));
        $this->assertNotNull((new StructuredKnowledgeRepository)->latestDocument(
            KnowledgeScope::forProject($accountB->id, $workspaceB->id, $projectB->id),
            'legacy_memory',
            $this->structuredUri($keyB),
        ));
        Storage::disk('local')->assertMissing($this->mappingPath($keyA));

        $store->forget($keyB);
        $this->assertNull((new StructuredKnowledgeRepository)->latestDocument(
            KnowledgeScope::forProject($accountB->id, $workspaceB->id, $projectB->id),
            'legacy_memory',
            $this->structuredUri($keyB),
        ));
        Storage::disk('local')->assertMissing($this->mappingPath($keyB));
    }

    #[Test]
    public function reusing_one_legacy_key_in_a_new_project_deactivates_only_its_previous_scope(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        [$account, $workspace, $projectA] = $this->createTenant('Scope A');
        $projectB = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $projectA->client_id,
            'name' => 'Project Scope B',
            'stage' => 1,
            'status' => 'active',
        ]);
        [$otherAccount, $otherWorkspace, $otherProject] = $this->createTenant('Unrelated');
        $key = 'agent.strategist.ws'.$workspace->id.'.shared-key';
        $otherKey = 'agent.strategist.ws'.$otherWorkspace->id.'.shared-key';
        $store = new KnowledgeStore(new StructuredKnowledgeRepository);

        $store->remember($key, [
            'workspace_id' => $workspace->id,
            'project_id' => $projectA->id,
            'value' => 'first scope',
        ]);
        $store->remember($otherKey, [
            'workspace_id' => $otherWorkspace->id,
            'project_id' => $otherProject->id,
            'value' => 'unrelated scope',
        ]);
        $store->remember($key, [
            'workspace_id' => $workspace->id,
            'project_id' => $projectB->id,
            'value' => 'second scope',
        ]);

        $repository = new StructuredKnowledgeRepository;
        $this->assertNull($repository->latestDocument(
            KnowledgeScope::forProject($account->id, $workspace->id, $projectA->id),
            'legacy_memory',
            $this->structuredUri($key),
        ));
        $this->assertNotNull($repository->latestDocument(
            KnowledgeScope::forProject($account->id, $workspace->id, $projectB->id),
            'legacy_memory',
            $this->structuredUri($key),
        ));
        $this->assertNotNull($repository->latestDocument(
            KnowledgeScope::forProject($otherAccount->id, $otherWorkspace->id, $otherProject->id),
            'legacy_memory',
            $this->structuredUri($otherKey),
        ));

        $store->forget($key);

        $this->assertNull($repository->latestDocument(
            KnowledgeScope::forProject($account->id, $workspace->id, $projectB->id),
            'legacy_memory',
            $this->structuredUri($key),
        ));
        $this->assertNotNull($repository->latestDocument(
            KnowledgeScope::forProject($otherAccount->id, $otherWorkspace->id, $otherProject->id),
            'legacy_memory',
            $this->structuredUri($otherKey),
        ));
    }

    private function enableDualWrite(): void
    {
        config()->set('services.knowledge.structured_store', true);
        config()->set('services.knowledge.dual_write', true);
    }

    private function legacyPath(string $key): string
    {
        $safe = preg_replace('/[^a-z0-9._-]+/i', '_', $key) ?: 'unknown';

        return 'ai-knowledge/'.$safe.'.json';
    }

    private function structuredUri(string $key): string
    {
        return 'legacy://sha256/'.hash('sha256', $key);
    }

    private function lockPath(string $key): string
    {
        return Storage::disk('local')->path(
            'ai-knowledge/.locks/'.hash('sha256', $this->legacyPath($key)).'.lock',
        );
    }

    private function mappingPath(string $key): string
    {
        return 'ai-knowledge/.structured-map/'.hash('sha256', $this->legacyPath($key)).'.json';
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
