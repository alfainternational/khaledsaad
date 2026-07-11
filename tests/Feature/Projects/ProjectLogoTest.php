<?php

namespace Tests\Feature\Projects;

use App\Domain\Account\Models\Account;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectLogoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_upload_project_logo_and_api_returns_its_url(): void
    {
        Storage::fake('public');
        [$owner, $workspace, $project] = $this->scenario();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'client_id' => $project->client_id,
                'stage' => $project->stage,
                'status' => $project->status,
                'sector' => $project->sector,
                'logo' => UploadedFile::fake()->image('project-logo.png', 300, 300),
            ])
            ->assertRedirectToRoute('projects.index');

        $project->refresh();

        $this->assertNotNull($project->logo_path);
        Storage::disk('public')->assertExists($project->logo_path);

        $this->app['auth']->guard('web')->logout();
        $this->flushSession();

        $token = $owner->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id)
            ->assertOk()
            ->assertJsonPath('data.logo_path', $project->logo_path)
            ->assertJsonPath('data.logo_url', Storage::disk('public')->url($project->logo_path));
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Project}
     */
    private function scenario(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'public_id' => (string) Str::ulid(),
            'owner_user_id' => $owner->id,
            'name' => 'Logo Account',
            'billing_email' => 'logo@example.com',
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'public_id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => 'Logo Workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $client = Client::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'name' => 'Logo Client',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Logo Project',
            'stage' => 2,
            'status' => 'active',
            'sector' => 'ecommerce',
        ]);

        return [$owner, $workspace, $project];
    }
}
