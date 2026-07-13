<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessKnowledgeUploadsCommandTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    public function test_empty_upload_queue_is_successful(): void
    {
        $this->artisan('knowledge:process-uploads')
            ->expectsOutput('Indexed: 0; queued: 0; failed: 0')
            ->assertSuccessful();
    }

    public function test_binary_stored_uploads_are_dispatched_instead_of_sent_to_the_text_extractor(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id, 'name' => 'Uploads',
            'billing_email' => $user->email, 'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id, 'name' => 'Uploads', 'type' => 'team', 'status' => 'active',
        ]);
        $client = Client::query()->create(['workspace_id' => $workspace->id, 'name' => 'Uploads', 'status' => 'active']);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id, 'client_id' => $client->id,
            'name' => 'Uploads', 'stage' => 1, 'status' => 'active',
        ]);
        $path = 'knowledge-uploads/pending.pdf';
        Storage::disk('local')->put($path, '%PDF pending');
        KnowledgeUpload::query()->create([
            'public_id' => 'upl_pending_pdf', 'account_id' => $account->id,
            'workspace_id' => $workspace->id, 'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id, 'disk' => 'local', 'path' => $path,
            'original_name' => 'pending.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf',
            'byte_size' => 12, 'sha256' => hash('sha256', '%PDF pending'), 'status' => 'stored',
        ]);

        $this->artisan('knowledge:process-uploads')
            ->expectsOutput('Indexed: 0; queued: 1; failed: 0')
            ->assertSuccessful();

        $this->assertDatabaseHas('knowledge_uploads', ['public_id' => 'upl_pending_pdf', 'status' => 'needs_worker']);
        $this->assertDatabaseHas('intelligence_jobs', ['type' => 'document_extract', 'status' => 'queued']);
    }

    public function test_processing_limit_is_bounded_for_shared_hosting(): void
    {
        $this->artisan('knowledge:process-uploads', ['--limit' => 101])
            ->expectsOutput('The upload processing limit must be between 1 and 100.')
            ->assertExitCode(2);
    }
}
