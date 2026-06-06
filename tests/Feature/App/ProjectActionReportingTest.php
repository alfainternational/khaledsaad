<?php

namespace Tests\Feature\App;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectActionReportingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reports_page_surfaces_the_next_decision_for_each_project(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Reports Account',
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
            'name' => 'Reports Workspace',
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

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Reports Client',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Reports Project',
            'stage' => 3,
            'status' => 'active',
        ]);

        app(ProjectMarketingBriefStore::class)->put($workspace, $project, [
            'business' => [
                'summary' => 'نظام تسويق للمشاريع القائمة',
                'offer' => 'خطة ورسائل ومحتوى',
                'market' => 'السعودية',
            ],
            'audience' => [
                'ideal_customer' => 'أصحاب مشاريع قائمة',
                'pain_points' => 'تشتت تسويقي وقرارات غير واضحة',
            ],
            'goals' => [
                'primary_goal' => 'رفع جودة العملاء المحتملين',
                'success_metric' => 'عدد الاستفسارات المؤهلة',
            ],
            'current_marketing' => [
                'channels' => 'إنستغرام، واتساب',
                'current_state' => 'حضور متقطع بلا خطة ثابتة',
            ],
            'brand' => [
                'voice' => 'واضح، مباشر، عملي',
            ],
        ]);

        AuditRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'trigger_source' => 'manual',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'summary_json' => [
                'headline' => 'تقرير intelligence موثّق وجاهز',
                'executive_score' => 68,
            ],
            'report_json' => [
                'executive_scores' => [
                    'executive' => 68,
                    'website' => 74,
                    'social' => 58,
                    'seo' => 49,
                    'trust' => 70,
                    'conversion' => 61,
                    'ads_readiness' => 57,
                    'ai_visibility' => 52,
                    'competition' => 66,
                    'lead_readiness' => 64,
                ],
                'analysis_integrity' => [
                    'status' => 'verified',
                ],
                'honest_diagnosis' => [
                    'الأساس SEO ما زال عملياً لكنه غير منضبط بما يكفي للفهرسة والوضوح.',
                ],
            ],
            'payload_json' => [],
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('القرار التالي لكل مشروع')
            ->assertSee('ابدأ بالتشخيص')
            ->assertSee('التشخيص')
            ->assertSee('أكثر الأبعاد ضعفاً')
            ->assertSee('Seo')
            ->assertSee('حالة موثوقية التحليل')
            ->assertSee('Verified');
    }
}
