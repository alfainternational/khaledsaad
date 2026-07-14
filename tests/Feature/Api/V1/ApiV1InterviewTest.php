<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiV1InterviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function interview_saves_canonical_values_and_downstream_tool_prefills_via_api(): void
    {
        [$owner, $workspace, $project] = $this->scenario();
        Tool::query()->updateOrCreate(
            ['code' => 'pricing-strategy'],
            [
                'name' => 'Pricing Strategy',
                'description' => 'Pricing.',
                'stage' => 3,
                'sort_order' => 1,
                'status' => 'published',
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
            ],
        );

        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id;

        // شاشة المقابلة تُعيد الأسئلة.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/interview')
            ->assertOk()
            ->assertJsonPath('data.questions.0.key', 'ideal_customer');

        // حفظ الإجابات (تُخزَّن canonical).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($base.'/interview', [
                'answers' => [
                    'offer' => 'نظام محتوى شهري يجلب طلبات',
                    'ideal_customer' => 'أصحاب المطاعم الصغيرة',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        // أداة التسعير تُلقّم حقلها تلقائياً من قيمة المقابلة (الحلقة المُغلقة عبر API).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($base.'/tools/pricing-strategy')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'offer',
        ]);
    }

    #[Test]
    public function interview_rejects_empty_answers_with_422(): void
    {
        [$owner, $workspace, $project] = $this->scenario();
        $token = $owner->createToken('test')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/projects/'.$project->public_id;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($base.'/interview', [
                'answers' => ['offer' => '   '],
            ])
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Project}
     */
    private function scenario(): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'API Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'API WS',
            'type' => 'team',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        app(OnboardingState::class)->markCompleted($workspace);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'API Project',
            'stage' => 2,
            'status' => 'active',
        ]);

        return [$owner, $workspace, $project];
    }
}
