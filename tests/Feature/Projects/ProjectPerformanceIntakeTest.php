<?php

namespace Tests\Feature\Projects;

use App\Domain\Account\Models\Account;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectPerformanceIntakeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_save_a_manual_performance_snapshot_and_see_calculated_metrics(): void
    {
        [$owner, $workspace, $project] = $this->scenario();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.performance.store', $project), [
                'period_start' => now()->subDays(7)->toDateString(),
                'period_end' => now()->toDateString(),
                'spend' => 1000,
                'leads' => 20,
                'sales' => 4,
                'revenue' => 3000,
                'notes' => 'حملة اختبار الأداء',
            ])
            ->assertRedirectToRoute('projects.show', $project);

        $snapshot = WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->where('key', 'performance_snapshot')
            ->firstOrFail();

        $this->assertEquals(50.0, $snapshot->value_json['cpl']);
        $this->assertEquals(3.0, $snapshot->value_json['roas']);
        $this->assertEquals(20.0, $snapshot->value_json['conversion_rate']);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('قياس الأداء السريع', false)
            ->assertSee('تكلفة العميل المحتمل', false)
            ->assertSee('50.00', false)
            ->assertSee('ROAS', false)
            ->assertSee('3.00x', false)
            ->assertSee('معدل التحويل', false)
            ->assertSee('20.00%', false)
            ->assertSee('حملة اختبار الأداء', false);
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
            'name' => 'Performance Account',
            'billing_email' => 'performance@example.com',
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'public_id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => 'Performance Workspace',
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
            'name' => 'Performance Client',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Performance Project',
            'stage' => 5,
            'status' => 'active',
            'sector' => 'ecommerce',
        ]);

        return [$owner, $workspace, $project];
    }
}
