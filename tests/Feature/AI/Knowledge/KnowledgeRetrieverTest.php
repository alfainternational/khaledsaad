<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeRetriever;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeRetrieverTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function project_retrieval_merges_its_scope_workspace_and_global_without_cross_tenant_leaks(): void
    {
        [$account, $workspaceA, $projectA] = $this->tenant('A');
        [, $workspaceB, $projectB] = $this->tenant('B');

        $repository = app(StructuredKnowledgeRepository::class);
        $this->store($repository, KnowledgeScope::forProject($account->id, $workspaceA->id, $projectA->id), 'project-a', 100);
        $this->store($repository, KnowledgeScope::forWorkspace($account->id, $workspaceA->id), 'workspace-a', 80);
        $this->store($repository, KnowledgeScope::global(), 'global', 60);
        $this->store($repository, KnowledgeScope::forProject(
            (int) $workspaceB->account_id,
            $workspaceB->id,
            $projectB->id,
        ), 'project-b-secret', 100);

        $evidence = app(KnowledgeRetriever::class)->retrieve(
            KnowledgeScope::forProject($account->id, $workspaceA->id, $projectA->id),
            'مؤشر الياقوت',
            10,
        );

        $this->assertSame(['project-a', 'workspace-a', 'global'], $evidence->pluck('sourceTitle')->all());
        $this->assertSame(['project', 'workspace', 'global'], $evidence->pluck('visibility')->all());
        $this->assertSame(
            $evidence->count(),
            $evidence->pluck('citation')->unique()->count(),
        );
        $this->assertFalse($evidence->contains(
            fn ($item): bool => str_contains($item->sourceTitle, 'secret'),
        ));
    }

    #[Test]
    public function retrieval_is_token_aware_bounded_and_deterministic(): void
    {
        [$account, $workspace, $project] = $this->tenant('Token');
        $scope = KnowledgeScope::forProject($account->id, $workspace->id, $project->id);
        $repository = app(StructuredKnowledgeRepository::class);
        $this->store($repository, $scope, 'first', 90, 'تحليل سلوك العملاء ومؤشر الاحتفاظ');
        $this->store($repository, $scope, 'second', 90, 'مؤشر الاحتفاظ ونمو العملاء');

        $retriever = app(KnowledgeRetriever::class);
        $first = $retriever->retrieve($scope, 'تحليل الاحتفاظ المفقود', 1);
        $second = $retriever->retrieve($scope, 'تحليل الاحتفاظ المفقود', 1);

        $this->assertCount(1, $first);
        $this->assertSame('first', $first->first()->sourceTitle);
        $this->assertEquals($first->toArray(), $second->toArray());
    }

    private function store(
        StructuredKnowledgeRepository $repository,
        KnowledgeScope $scope,
        string $title,
        int $trust,
        string $content = 'هذا الدليل يحتوي على مؤشر الياقوت لاتخاذ القرار.',
    ): void {
        $repository->storeDocument(
            $scope,
            'test',
            'test://'.$title,
            $title,
            $content,
            [['heading' => 'Evidence', 'content' => $content, 'locator' => ['line' => 1]]],
            $trust,
        );
    }

    /** @return array{Account, Workspace, Project} */
    private function tenant(string $suffix): array
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
