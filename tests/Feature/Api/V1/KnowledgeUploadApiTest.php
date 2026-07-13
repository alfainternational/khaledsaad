<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Knowledge\Models\KnowledgeUploadSession;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeUploadApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_project_editor_can_upload_list_and_delete_private_knowledge(): void
    {
        Storage::fake('local');
        [$user, $workspace, $project] = $this->tenant('Owner');
        $token = $user->createToken('knowledge-test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id.'/knowledge/uploads';

        $created = $this->withToken($token)->post($base, [
            'file' => UploadedFile::fake()->createWithContent(
                'market-notes.txt',
                'مؤشر السندس يثبت ارتفاع الاحتفاظ إلى 83.',
            ),
        ], ['Accept' => 'application/json']);

        $created
            ->assertCreated()
            ->assertJsonPath('data.status', 'indexed')
            ->assertJsonPath('data.original_name', 'market-notes.txt');
        $publicId = $created->json('data.public_id');
        $this->assertNotEmpty($publicId);
        $this->assertDatabaseHas('knowledge_uploads', [
            'public_id' => $publicId,
            'project_id' => $project->id,
        ]);

        $this->withToken($token)
            ->getJson($base)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonMissingPath('data.0.disk');

        $this->withToken($token)
            ->deleteJson($base.'/'.$publicId)
            ->assertNoContent();

        $this->assertDatabaseMissing('knowledge_uploads', ['public_id' => $publicId]);
        $this->assertDatabaseHas('knowledge_documents', ['status' => 'superseded']);
        Storage::disk('local')->assertMissing(
            'knowledge-uploads/'.$workspace->account_id.'/'.$workspace->id.'/'.$project->id.'/'.hash('sha256', 'مؤشر السندس يثبت ارتفاع الاحتفاظ إلى 83.').'.txt',
        );
    }

    #[Test]
    public function uploads_are_not_visible_across_projects(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $projectA] = $this->tenant('Isolation');
        $projectB = $this->project($workspace, 'B');
        $baseA = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$projectA->public_id.'/knowledge/uploads';
        $baseB = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$projectB->public_id.'/knowledge/uploads';
        $ownerToken = $owner->createToken('owner')->plainTextToken;

        $this->withToken($ownerToken)->post($baseA, [
            'file' => UploadedFile::fake()->createWithContent('private.txt', 'دليل خاص بالمشروع الأول'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->withToken($ownerToken)->getJson($baseB)->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function workspace_viewers_cannot_upload_project_knowledge(): void
    {
        Storage::fake('local');
        [, $workspace, $project] = $this->tenant('Viewer');
        $viewer = User::factory()->create();
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
            'invited_at' => now(),
        ]);
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id.'/knowledge/uploads';

        $this->withToken($viewer->createToken('viewer')->plainTextToken)
            ->post($base, [
                'file' => UploadedFile::fake()->createWithContent('denied.txt', 'غير مسموح'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    #[Test]
    public function binary_content_returns_a_stable_extraction_error_and_retains_the_failed_record(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->tenant('Binary');
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id.'/knowledge/uploads';

        $this->withToken($owner->createToken('owner')->plainTextToken)
            ->post($base, [
                'file' => UploadedFile::fake()->createWithContent('binary.txt', "valid\0binary"),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'KNOWLEDGE_EXTRACTION_FAILED')
            ->assertJsonPath('errors.file.0', 'binary_content')
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('knowledge_uploads', [
            'project_id' => $project->id,
            'status' => 'failed',
            'error_code' => 'binary_content',
        ]);
    }

    #[Test]
    public function heavy_documents_are_stored_privately_and_queued_for_the_private_worker(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->tenant('Heavy');
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id.'/knowledge/uploads';

        $response = $this->withToken($owner->createToken('owner')->plainTextToken)
            ->post($base, [
                'file' => UploadedFile::fake()->create('research.pdf', 200, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'needs_worker');

        $this->assertDatabaseHas('intelligence_jobs', [
            'project_id' => $project->id,
            'type' => 'document_extract',
            'status' => 'queued',
        ]);
        $payload = IntelligenceJob::query()->firstOrFail()->payload_json;
        $this->assertSame($response->json('data.public_id'), $payload['upload_public_id']);
        $this->assertArrayNotHasKey('path', $payload);
        $this->assertArrayNotHasKey('disk', $payload);
    }

    #[Test]
    public function retrying_a_heavy_upload_reuses_its_active_job_and_redispatches_after_failure(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->tenant('Retry Heavy');
        $token = $owner->createToken('owner')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id.'/knowledge/uploads';
        $created = $this->withToken($token)->post($base, [
            'file' => UploadedFile::fake()->create('retry.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertAccepted();
        $uploadId = $created->json('data.public_id');
        $job = IntelligenceJob::query()->sole();

        $this->withToken($token)->postJson($base.'/'.$uploadId.'/retry')->assertAccepted();
        $this->assertDatabaseCount('intelligence_jobs', 1);

        $job->update(['status' => 'failed']);
        $this->withToken($token)->postJson($base.'/'.$uploadId.'/retry')->assertAccepted();
        $this->assertDatabaseCount('intelligence_jobs', 2);
        $this->assertSame(1, IntelligenceJob::query()->where('status', 'queued')->count());
    }

    #[Test]
    public function chunked_upload_routes_are_hidden_while_the_feature_is_disabled(): void
    {
        config()->set('services.knowledge.chunked_uploads', false);
        [$owner, $workspace, $project] = $this->tenant('Chunk Disabled');
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id.'/knowledge/upload-sessions';

        $this->withToken($owner->createToken('owner')->plainTextToken)
            ->postJson($base, [
                'original_name' => 'large.pdf',
                'mime_type' => 'application/pdf',
                'byte_size' => 4,
                'sha256' => hash('sha256', 'test'),
            ])
            ->assertNotFound();
    }

    #[Test]
    public function a_chunked_upload_is_assembled_verified_and_dispatched(): void
    {
        Storage::fake('local');
        config()->set('services.knowledge.chunked_uploads', true);
        config()->set('services.knowledge.chunk_bytes', 3);
        [$owner, $workspace, $project] = $this->tenant('Chunk Complete');
        $token = $owner->createToken('owner')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id.'/knowledge/upload-sessions';
        $content = 'abcdef';

        $created = $this->withToken($token)->postJson($base, [
            'original_name' => 'large.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => strlen($content),
            'sha256' => hash('sha256', $content),
        ])->assertCreated()->assertJsonPath('data.chunk_size', 3);
        $sessionId = $created->json('data.public_id');

        $this->withToken($token)->call('PUT', $base.'/'.$sessionId.'/chunks/1', [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
        ], 'def')->assertNoContent();
        $this->withToken($token)->call('PUT', $base.'/'.$sessionId.'/chunks/0', [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
        ], 'abc')->assertNoContent();

        $completed = $this->withToken($token)->postJson($base.'/'.$sessionId.'/complete')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'needs_worker');

        $this->assertDatabaseHas('knowledge_uploads', [
            'public_id' => $completed->json('data.public_id'),
            'sha256' => hash('sha256', $content),
            'byte_size' => strlen($content),
        ]);
        $this->assertDatabaseHas('knowledge_upload_sessions', ['public_id' => $sessionId, 'status' => 'completed']);
        $this->assertDatabaseHas('intelligence_jobs', ['project_id' => $project->id, 'type' => 'document_extract']);
    }

    #[Test]
    public function expired_chunked_upload_sessions_are_removed_from_private_storage(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $project] = $this->tenant('Chunk Cleanup');
        $session = KnowledgeUploadSession::query()->create([
            'public_id' => 'ups_expired',
            'account_id' => $workspace->account_id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'uploaded_by_user_id' => $owner->id,
            'disk' => 'local',
            'path' => 'knowledge-upload-sessions/expired',
            'original_name' => 'expired.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'byte_size' => 3,
            'chunk_size' => 3,
            'chunk_count' => 1,
            'sha256' => hash('sha256', 'old'),
            'status' => 'open',
            'expires_at' => now()->subMinute(),
        ]);
        Storage::disk('local')->put($session->path.'/chunks/0.part', 'old');

        $this->artisan('knowledge:cleanup-upload-sessions')->expectsOutput('Removed: 1')->assertSuccessful();

        $this->assertDatabaseMissing('knowledge_upload_sessions', ['public_id' => 'ups_expired']);
        Storage::disk('local')->assertMissing($session->path.'/chunks/0.part');
    }

    /** @return array{User, Workspace, Project} */
    private function tenant(string $suffix): array
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Account '.$suffix,
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Workspace '.$suffix,
            'type' => 'team',
            'status' => 'active',
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        return [$user, $workspace, $this->project($workspace, 'A')];
    }

    private function project(Workspace $workspace, string $suffix): Project
    {
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client '.$suffix,
            'status' => 'active',
        ]);

        return Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Project '.$suffix,
            'stage' => 1,
            'status' => 'active',
        ]);
    }
}
