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
use DateTimeInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LegacyKnowledgeLifecycleTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function global_import_dual_write_and_forget_share_one_source_identity(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        $key = 'playbook.lifecycle';
        $this->writeLegacyFile($key, ['value' => 'imported']);

        $this->artisan('knowledge:import-legacy')->assertSuccessful();
        (new KnowledgeStore(new StructuredKnowledgeRepository))->remember($key, ['value' => 'updated']);

        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertSame(1, KnowledgeDocument::query()->where('status', 'active')->count());

        (new KnowledgeStore(new StructuredKnowledgeRepository))->forget($key);

        $this->assertSame(0, KnowledgeDocument::query()->where('status', 'active')->count());
    }

    #[Test]
    public function scoped_import_dual_write_and_forget_share_one_validated_source_identity(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        [$account, $workspace, $project] = $this->createTenant('Lifecycle');
        $key = 'agent.strategist.ws'.$workspace->id.'.lifecycle';
        $data = [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'value' => 'private',
        ];
        $this->writeLegacyFile($key, $data);

        $this->artisan('knowledge:import-legacy')->assertSuccessful();
        (new KnowledgeStore(new StructuredKnowledgeRepository))->remember($key, $data + ['updated' => true]);

        $this->assertDatabaseCount('knowledge_sources', 1);
        $scope = KnowledgeScope::forProject($account->id, $workspace->id, $project->id);
        $source = DB::table('knowledge_sources')->sole();
        $this->assertSame($scope->key(), $source->scope_key);

        (new KnowledgeStore(new StructuredKnowledgeRepository))->forget($key);

        $this->assertSame(0, KnowledgeDocument::query()->where('status', 'active')->count());
    }

    #[Test]
    public function rotating_app_key_does_not_break_strict_tenant_deletion_mapping(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        [$account, $workspace, $project] = $this->createTenant('Rotation');
        $key = 'agent.strategist.ws'.$workspace->id.'.rotation';
        $store = new KnowledgeStore(new StructuredKnowledgeRepository);
        $store->remember($key, [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'value' => 'private',
        ]);
        Storage::disk('local')->delete('ai-knowledge/'.$key.'.json');
        $oldKey = (string) config('app.key');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('services.knowledge.mapping_previous_keys', [$oldKey]);

        $store->forget($key);

        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertSame(0, KnowledgeDocument::query()->where('status', 'active')->count());
        $this->assertDatabaseHas('knowledge_sources', [
            'scope_key' => KnowledgeScope::forProject($account->id, $workspace->id, $project->id)->key(),
        ]);
    }

    #[Test]
    public function tampered_scope_mapping_cannot_deactivate_another_tenant(): void
    {
        Storage::fake('local');
        $this->enableDualWrite();
        [$accountA, $workspaceA, $projectA] = $this->createTenant('Signed A');
        [$accountB, $workspaceB, $projectB] = $this->createTenant('Signed B');
        $key = 'agent.strategist.ws'.$workspaceA->id.'.signed';
        $uri = 'legacy://sha256/'.hash('sha256', $key);
        $repository = new StructuredKnowledgeRepository;
        $store = new KnowledgeStore($repository);
        $store->remember($key, [
            'workspace_id' => $workspaceA->id,
            'project_id' => $projectA->id,
            'value' => 'tenant A',
        ]);
        $repository->storeDocument(
            KnowledgeScope::forProject($accountB->id, $workspaceB->id, $projectB->id),
            'legacy_memory',
            $uri,
            'Tenant B',
            'value: "tenant B"',
            [['content' => 'value: "tenant B"', 'locator' => ['canonical_uri' => $uri]]],
        );
        $mappingPath = 'ai-knowledge/.structured-map/'.hash('sha256', 'ai-knowledge/'.$key.'.json').'.json';
        $mapping = json_decode(Storage::disk('local')->get($mappingPath), true, 512, JSON_THROW_ON_ERROR);
        $mapping['account_id'] = $accountB->id;
        $mapping['workspace_id'] = $workspaceB->id;
        $mapping['project_id'] = $projectB->id;
        Storage::disk('local')->put($mappingPath, json_encode($mapping, JSON_THROW_ON_ERROR));
        Log::shouldReceive('notice')->once()->with(
            'Structured knowledge mirror skipped.',
            Mockery::on(fn (array $context): bool => $context === [
                'key_hash' => hash('sha256', $key),
                'reason' => 'scope_mapping_invalid',
            ]),
        );

        $store->forget($key);

        $this->assertSame(2, KnowledgeDocument::query()->where('status', 'active')->count());
        $this->assertDatabaseHas('knowledge_sources', [
            'scope_key' => KnowledgeScope::forProject($accountA->id, $workspaceA->id, $projectA->id)->key(),
        ]);
        $this->assertDatabaseHas('knowledge_sources', [
            'scope_key' => KnowledgeScope::forProject($accountB->id, $workspaceB->id, $projectB->id)->key(),
        ]);
    }

    #[Test]
    public function mysql_cross_process_two_writers_converge_while_sqlite_memory_is_covered_in_process(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite :memory: cannot share its database with subprocesses; lock behavior is covered in-process.');
        }

        $key = 'playbook.concurrent-'.bin2hex(random_bytes(6));
        $legacyPath = 'ai-knowledge/'.$key.'.json';
        $mappingPath = 'ai-knowledge/.structured-map/'.hash('sha256', $legacyPath).'.json';
        $lockPath = 'ai-knowledge/.locks/'.hash('sha256', $legacyPath).'.lock';
        $disk = Storage::disk('local');
        $disk->delete([$legacyPath, $mappingPath, $lockPath]);
        $script = <<<'PHP'
$base = $argv[1];
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config()->set('services.knowledge.structured_store', true);
config()->set('services.knowledge.dual_write', true);
(new App\Domain\AI\Kernel\Knowledge\KnowledgeStore(
    new App\Domain\AI\Knowledge\StructuredKnowledgeRepository,
))->remember($argv[2], ['writer' => $argv[3]]);
PHP;
        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ];
        $writers = [
            new Process([PHP_BINARY, '-r', $script, base_path(), $key, 'one'], base_path(), $environment),
            new Process([PHP_BINARY, '-r', $script, base_path(), $key, 'two'], base_path(), $environment),
        ];

        foreach ($writers as $writer) {
            $writer->setTimeout(20);
        }

        try {
            foreach ($writers as $writer) {
                $writer->start();
            }
            foreach ($writers as $writer) {
                $this->assertSame(0, $writer->wait(), $writer->getErrorOutput());
            }

            $memory = (new KnowledgeStore)->recall($key);
            $document = (new StructuredKnowledgeRepository)->latestDocument(
                KnowledgeScope::global(),
                'legacy_memory',
                'legacy://sha256/'.hash('sha256', $key),
            );
            $this->assertNotNull($memory);
            $this->assertNotNull($document);
            $this->assertSame('writer: '.json_encode($memory['data']['writer']), $document->content);
            $this->assertDatabaseCount('knowledge_sources', 1);
            $this->assertSame(1, KnowledgeDocument::query()->where('status', 'active')->count());
        } finally {
            foreach ($writers as $writer) {
                if ($writer->isRunning()) {
                    $writer->stop(1);
                }
            }
            $disk->delete([$legacyPath, $mappingPath, $lockPath]);
        }
    }

    private function enableDualWrite(): void
    {
        config()->set('services.knowledge.structured_store', true);
        config()->set('services.knowledge.dual_write', true);
    }

    /** @param array<string, mixed> $data */
    private function writeLegacyFile(string $key, array $data): void
    {
        Storage::disk('local')->put('ai-knowledge/'.$key.'.json', json_encode([
            'key' => $key,
            'data' => $data,
            'learned_at' => now()->format(DateTimeInterface::ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array{Account, Workspace, Project} */
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
