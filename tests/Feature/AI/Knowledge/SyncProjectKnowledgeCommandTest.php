<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\InvalidProjectKnowledgeData;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use App\Domain\AI\Knowledge\ProjectKnowledgeSnapshotBuilder;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SyncProjectKnowledgeCommandTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function it_syncs_changes_idempotently_and_keeps_project_scopes_isolated(): void
    {
        $first = $this->project('Alpha', 'محتوى عربي');
        $second = $this->project('Beta', 'بيانات أخرى');

        $this->artisan('knowledge:sync-projects')->expectsOutputToContain('Synced: 2; unchanged: 0; failed: 0')->assertSuccessful();
        $this->artisan('knowledge:sync-projects')->expectsOutputToContain('Synced: 0; unchanged: 2; failed: 0')->assertSuccessful();

        $this->assertDatabaseCount('knowledge_sources', 2);
        $this->assertDatabaseCount('knowledge_documents', 2);
        $this->assertDatabaseHas('knowledge_sources', [
            'workspace_id' => $first->workspace_id, 'project_id' => $first->id,
            'kind' => 'project_snapshot', 'canonical_uri' => 'project://'.$first->public_id.'/snapshot', 'trust_score' => 100,
        ]);

        $first->update(['sector' => 'قطاع متغير']);
        $this->artisan('knowledge:sync-projects', ['--project' => $first->id])
            ->expectsOutputToContain('Synced: 1; unchanged: 0; failed: 0')->assertSuccessful();

        $this->assertSame(2, KnowledgeDocument::query()->whereHas('source', fn ($q) => $q->where('project_id', $first->id))->count());
        $this->assertSame(1, KnowledgeDocument::query()->whereHas('source', fn ($q) => $q->where('project_id', $second->id))->count());
        $this->assertSame(1, KnowledgeSource::query()->where('project_id', $first->id)->count());
    }

    #[Test]
    public function partial_projects_are_synced_without_leaking_credentials(): void
    {
        $project = $this->project('Partial', '');
        config(['services.example.secret' => 'CONFIG-SECRET']);

        $this->artisan('knowledge:sync-projects', ['--project' => $project->id])
            ->expectsOutputToContain('Synced: 1; unchanged: 0; failed: 0')->assertSuccessful();

        $content = KnowledgeDocument::query()->firstOrFail()->content;
        $this->assertStringContainsString('Project Partial', $content);
        $this->assertStringNotContainsString('CONFIG-SECRET', $content);
    }

    #[Test]
    public function infrastructure_failure_aborts_the_batch_immediately(): void
    {
        $first = $this->project('Failure', 'تقنية');
        $second = $this->project('NeverReached', 'تجارة');
        $repository = new class extends StructuredKnowledgeRepository
        {
            public int $calls = 0;

            public function latestDocument(KnowledgeScope $scope, string $kind, string $canonicalUri): ?KnowledgeDocument
            {
                $this->calls++;
                throw new RuntimeException('database unavailable');
            }
        };
        $this->app->instance(StructuredKnowledgeRepository::class, $repository);

        $this->artisan('knowledge:sync-projects')
            ->expectsOutputToContain('Project knowledge synchronization failed.')
            ->assertFailed();

        $this->assertSame(1, $repository->calls);
        $this->assertDatabaseMissing('knowledge_sources', ['project_id' => $first->id]);
        $this->assertDatabaseMissing('knowledge_sources', ['project_id' => $second->id]);
    }

    #[Test]
    public function expected_invalid_project_data_is_counted_without_stopping_the_batch(): void
    {
        $invalid = $this->project('Invalid', 'تقنية');
        $this->project('Valid', 'تجارة');
        $this->app->instance(ProjectKnowledgeSnapshotBuilder::class, new class($invalid->id) extends ProjectKnowledgeSnapshotBuilder
        {
            public function __construct(private readonly int $invalidId) {}

            public function build(Project $project): array
            {
                if ($project->id === $this->invalidId) {
                    throw new InvalidProjectKnowledgeData('invalid project data');
                }

                return parent::build($project);
            }
        });

        $this->artisan('knowledge:sync-projects')
            ->expectsOutputToContain('Synced: 1; unchanged: 0; failed: 1')
            ->assertFailed();
        $this->assertDatabaseCount('knowledge_sources', 1);
    }

    #[Test]
    public function explicit_domain_validation_failure_is_safe_and_does_not_stop_later_projects(): void
    {
        $broken = $this->project('Broken', 'تقنية');
        $valid = $this->project('Later', 'تجارة');
        $this->app->instance(ProjectKnowledgeSnapshotBuilder::class, new class($broken->id) extends ProjectKnowledgeSnapshotBuilder
        {
            public function __construct(private readonly int $brokenId) {}

            public function build(Project $project): array
            {
                if ($project->id === $this->brokenId) {
                    throw new InvalidProjectKnowledgeData('sensitive failure detail');
                }

                return parent::build($project);
            }
        });

        $this->artisan('knowledge:sync-projects')
            ->expectsOutputToContain('Project '.$broken->id.' could not be synchronized.')
            ->doesntExpectOutputToContain('sensitive failure detail')
            ->expectsOutputToContain('Synced: 1; unchanged: 0; failed: 1')
            ->assertFailed();

        $this->assertDatabaseHas('knowledge_sources', ['project_id' => $valid->id]);
        $this->assertDatabaseMissing('knowledge_sources', ['project_id' => $broken->id]);
    }

    #[Test]
    public function an_explicit_missing_project_returns_a_safe_non_zero_result(): void
    {
        $this->artisan('knowledge:sync-projects', ['--project' => 999999])
            ->expectsOutputToContain('Project 999999 was not found.')
            ->assertFailed();
    }

    #[Test]
    public function latest_tool_run_lookup_has_a_portable_supporting_index(): void
    {
        $indexes = collect(Schema::getIndexes('tool_runs'));

        $this->assertTrue($indexes->contains(
            fn (array $index): bool => $index['columns'] === ['workspace_id', 'project_id', 'tool_code', 'id'],
        ));
    }

    private function project(string $suffix, ?string $sector): Project
    {
        $userId = DB::table('users')->insertGetId([
            'name' => "User {$suffix}", 'email' => strtolower($suffix).'@example.test', 'password' => 'secret',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $accountId = DB::table('accounts')->insertGetId([
            'public_id' => "account-{$suffix}", 'owner_user_id' => $userId, 'name' => "Account {$suffix}", 'billing_email' => strtolower($suffix).'@example.test',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspaceId = DB::table('workspaces')->insertGetId([
            'public_id' => "workspace-{$suffix}", 'account_id' => $accountId, 'name' => "Workspace {$suffix}",
            'type' => 'team', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Project::query()->create([
            'public_id' => "project-{$suffix}", 'workspace_id' => $workspaceId, 'name' => "Project {$suffix}",
            'stage' => 1, 'status' => 'active', 'sector' => $sector,
        ]);
    }
}
