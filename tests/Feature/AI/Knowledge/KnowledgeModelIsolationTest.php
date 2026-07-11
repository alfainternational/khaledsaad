<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeModelIsolationTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function sources_are_isolated_by_scope_and_expose_document_relations(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Knowledge Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        [$workspaceA, $projectA] = $this->createWorkspaceProject($account, 'A');
        [$workspaceB, $projectB] = $this->createWorkspaceProject($account, 'B');
        $scopeA = KnowledgeScope::forProject($account->id, $workspaceA->id, $projectA->id);
        $scopeB = KnowledgeScope::forProject($account->id, $workspaceB->id, $projectB->id);

        $sourceA = $this->createSource($scopeA, 'source-a');
        $sourceB = $this->createSource($scopeB, 'source-b');
        $globalSource = $this->createSource(KnowledgeScope::global(), 'source-global');
        $corruptedSource = $this->createSource($scopeA, 'source-corrupted', str_repeat('0', 64));

        $this->assertSame([$sourceA->id], KnowledgeSource::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertNotContains($sourceB->id, KnowledgeSource::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertNotContains($globalSource->id, KnowledgeSource::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertNotContains($corruptedSource->id, KnowledgeSource::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertSame([$globalSource->id], KnowledgeSource::query()->inScope(KnowledgeScope::global())->pluck('id')->all());

        [$document, $chunk] = $this->createDocumentChunk($sourceA, 'a');
        [$documentB, $chunkB] = $this->createDocumentChunk($sourceB, 'b');
        [$globalDocument, $globalChunk] = $this->createDocumentChunk($globalSource, 'global');

        $this->assertSame([$document->id], KnowledgeDocument::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertNotContains($documentB->id, KnowledgeDocument::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertNotContains($globalDocument->id, KnowledgeDocument::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertSame([$chunk->id], KnowledgeChunk::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertNotContains($chunkB->id, KnowledgeChunk::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertNotContains($globalChunk->id, KnowledgeChunk::query()->inScope($scopeA)->pluck('id')->all());
        $this->assertSame([$globalDocument->id], KnowledgeDocument::query()->inScope(KnowledgeScope::global())->pluck('id')->all());
        $this->assertSame([$globalChunk->id], KnowledgeChunk::query()->inScope(KnowledgeScope::global())->pluck('id')->all());

        $this->assertTrue($sourceA->documents->contains($document));
        $this->assertTrue($document->chunks->contains($chunk));
        $this->assertTrue($document->source->is($sourceA));
        $this->assertTrue($chunk->document->is($document));
    }

    #[Test]
    public function model_factories_derive_and_validate_the_persisted_hierarchy(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Hierarchy Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        [$workspaceA, $projectA] = $this->createWorkspaceProject($account, 'Hierarchy A');
        [$workspaceB] = $this->createWorkspaceProject($account, 'Hierarchy B');

        $workspaceScope = KnowledgeScope::fromWorkspace($workspaceA);
        $projectScope = KnowledgeScope::fromProject($projectA);

        $this->assertEquals(KnowledgeScope::forWorkspace($account->id, $workspaceA->id), $workspaceScope);
        $this->assertEquals(KnowledgeScope::forProject($account->id, $workspaceA->id, $projectA->id), $projectScope);

        $projectA->setRelation('workspace', $workspaceB);

        $this->expectException(InvalidArgumentException::class);
        KnowledgeScope::fromProject($projectA);
    }

    private function createDocumentChunk(KnowledgeSource $source, string $identity): array
    {
        $document = KnowledgeDocument::query()->create([
            'knowledge_source_id' => $source->id,
            'content_hash' => hash('sha256', "document-{$identity}"),
            'version' => 1,
            'title' => "Document {$identity}",
            'language' => 'en',
            'status' => 'ready',
            'content' => 'Stored document content.',
            'valid_from' => now(),
            'meta_json' => ['origin' => 'test'],
        ]);
        $chunk = KnowledgeChunk::query()->create([
            'knowledge_document_id' => $document->id,
            'position' => 0,
            'heading' => 'Introduction',
            'content' => 'Stored chunk content.',
            'token_count' => 4,
            'locator_json' => ['page' => 1],
        ]);

        return [$document, $chunk];
    }

    private function createWorkspaceProject(Account $account, string $suffix): array
    {
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

        return [$workspace, $project];
    }

    private function createSource(
        KnowledgeScope $scope,
        string $identity,
        ?string $scopeKey = null,
    ): KnowledgeSource {
        return KnowledgeSource::query()->create([
            'account_id' => $scope->accountId,
            'workspace_id' => $scope->workspaceId,
            'project_id' => $scope->projectId,
            'scope_key' => $scopeKey ?? $scope->key(),
            'kind' => 'manual',
            'canonical_uri' => "knowledge://{$identity}",
            'identity_hash' => hash('sha256', $identity),
            'trust_score' => 80,
            'visibility' => $scope->visibility,
            'meta_json' => ['identity' => $identity],
        ]);
    }
}
