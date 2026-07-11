<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Account\Models\Account;
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
