<?php

namespace Tests\Feature\Execution;

use App\Domain\Account\Models\Account;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\Recommendation;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutionUiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_list_recommendations_build_a_package_and_view_it(): void
    {
        [$owner, $workspace, $project, $recommendation] = $this->scenario();

        // 1) Recommendations page lists the recommendation with a convert action.
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.recommendations.index', $project))
            ->assertOk()
            ->assertSee('الموقع غير آمن (HTTP)')
            ->assertSee('حوّل لحزمة تنفيذ');

        // 2) Convert to an execution package.
        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.recommendations.package', [$project, $recommendation]));

        $package = ExecutionPackage::query()->where('recommendation_id', $recommendation->id)->firstOrFail();
        $response->assertRedirectToRoute('execution-packages.show', $package);
        $this->assertCount(4, $package->tasks);

        // 3) Package page renders its tasks and measurement plan.
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertOk()
            ->assertSee('صياغة المخرج النهائي')
            ->assertSee('خطة القياس');
    }

    #[Test]
    public function a_member_of_another_workspace_cannot_view_the_package(): void
    {
        [, , , $recommendation] = $this->scenario();
        $package = ExecutionPackage::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $recommendation->workspace_id,
            'project_id' => $recommendation->project_id,
            'recommendation_id' => $recommendation->id,
            'title' => 'حزمة',
            'status' => 'proposed',
        ]);

        $outsider = User::factory()->create();
        $otherAccount = Account::query()->create([
            'owner_user_id' => $outsider->id, 'name' => 'Other', 'billing_email' => $outsider->email, 'status' => 'active',
        ]);
        $otherWorkspace = Workspace::query()->create([
            'account_id' => $otherAccount->id, 'name' => 'Other WS', 'type' => 'personal', 'status' => 'active',
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $otherWorkspace->id, 'user_id' => $outsider->id, 'role' => 'owner', 'status' => 'active', 'invited_at' => now(),
        ]);

        $this->actingAs($outsider)
            ->withSession(['current_workspace_id' => $otherWorkspace->id])
            ->get(route('execution-packages.show', $package))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Project, 3: Recommendation}
     */
    private function scenario(): array
    {
        $owner = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Exec Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Exec Workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        $project = Project::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'name' => 'Exec Project',
            'stage' => 1,
            'status' => 'active',
            'sector' => 'general',
        ]);

        $recommendation = Recommendation::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'website',
            'title' => 'الموقع غير آمن (HTTP)',
            'priority' => 10,
            'severity' => 'high',
            'evidence' => 'الصفحة عبر HTTP.',
            'rationale' => 'فعّل HTTPS.',
            'estimated_impact' => 'high',
            'confidence' => 0.95,
            'status' => 'proposed',
        ]);

        return [$owner, $workspace, $project, $recommendation];
    }
}
