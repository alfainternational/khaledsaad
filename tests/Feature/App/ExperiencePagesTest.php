<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertSee('الاستوديو الذكي')
            ->assertSee('Marketing Intelligence');

        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('تقارير الذكاء التسويقي');

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

    #[Test]
    public function studio_page_query_growth_stays_bounded_with_multiple_projects(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Studio Account',
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
            'name' => 'Studio Workspace',
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

        $briefStore = app(ProjectMarketingBriefStore::class);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Studio Client',
            'status' => 'active',
        ]);

        foreach (range(1, 3) as $index) {
            $project = Project::query()->create([
                'workspace_id' => $workspace->id,
                'client_id' => $client->id,
                'name' => 'Studio Project '.$index,
                'stage' => 3,
                'status' => 'active',
                'sector' => 'b2b_services',
            ]);

            $briefStore->put($workspace, $project, [
                'business' => [
                    'summary' => 'مشروع '.$index,
                    'offer' => 'عرض '.$index,
                    'market' => 'السعودية',
                ],
                'audience' => [
                    'ideal_customer' => 'عملاء '.$index,
                ],
                'goals' => [
                    'primary_goal' => 'رفع الطلب المؤهل',
                ],
            ]);

            AuditRun::query()->create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'status' => 'completed',
                'trigger_source' => 'manual',
                'started_at' => now()->subMinutes(5),
                'completed_at' => now(),
                'summary_json' => [
                    'headline' => 'تقرير intelligence موثّق وجاهز',
                    'executive_score' => 70,
                ],
                'report_json' => [
                    'executive_scores' => ['executive' => 70],
                    'analysis_integrity' => ['status' => 'verified'],
                ],
                'payload_json' => [],
            ]);
        }

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('studio.index'))
            ->assertOk()
            ->assertSee('الاستوديو الذكي');

        $this->assertLessThan(30, $queryCount);
    }
}
