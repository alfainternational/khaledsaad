<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExperiencePagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_view_core_experience_pages(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Agency Account',
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
            'name' => 'Agency Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        app(OnboardingState::class)->markCompleted($workspace);

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('tools.index'))
            ->assertOk()
            ->assertSee('ابحث عن أداة');

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('studio.index'))
            ->assertOk()
            ->assertSee('الاستوديو الذكي');

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('تقارير المساحة');

        $tool = Tool::query()->where('code', 'diagnosis')->firstOrFail();

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('tools.show', $tool))
            ->assertOk()
            ->assertSee($tool->name);

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('المراجعات والاعتمادات');
    }
}
