<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Knowledge\Uploads\KnowledgeUploadIndexer;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeUploadIndexerTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function it_indexes_a_private_upload_idempotently_in_the_project_scope(): void
    {
        Storage::fake('local');
        [$user, $account, $workspace, $project] = $this->tenant();
        $path = 'knowledge-uploads/'.$project->id.'/evidence.txt';
        Storage::disk('local')->put($path, 'مؤشر الزمرد يساوي 81 في التقرير المعتمد.');

        $upload = KnowledgeUpload::query()->create([
            'public_id' => 'upl_test_001',
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'evidence.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'byte_size' => Storage::disk('local')->size($path),
            'sha256' => hash('sha256', Storage::disk('local')->get($path)),
            'status' => 'stored',
        ]);

        $indexer = app(KnowledgeUploadIndexer::class);
        $first = $indexer->index($upload);
        $second = $indexer->index($upload->fresh());

        $this->assertSame('indexed', $first->status);
        $this->assertSame($first->knowledge_source_id, $second->knowledge_source_id);
        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertCount(1, app(StructuredKnowledgeRepository::class)->searchText(
            KnowledgeScope::forProject($account->id, $workspace->id, $project->id),
            'الزمرد',
        ));
    }

    /** @return array{User, Account, Workspace, Project} */
    private function tenant(): array
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Upload Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Upload Workspace',
            'type' => 'team',
            'status' => 'active',
        ]);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Upload Client',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Upload Project',
            'stage' => 1,
            'status' => 'active',
        ]);

        return [$user, $account, $workspace, $project];
    }
}
