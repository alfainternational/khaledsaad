<?php

namespace Tests\Feature\App;

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

class FounderInterviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_saves_interview_answers_as_canonical_workspace_data(): void
    {
        [$owner, $workspace, $project] = $this->scenario();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('interview.store'), [
                'project_id' => $project->id,
                'answers' => [
                    'ideal_customer' => 'أصحاب المطاعم الصغيرة في الخرطوم',
                    'offer' => 'نظام محتوى شهري يجلب طلبات',
                    'positioning' => 'أنفّذ المحتوى جاهزاً ومربوطاً بالمبيعات',
                    'market' => '',
                ],
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'ideal_customer',
        ]);

        $row = \App\Domain\WorkspaceData\Models\WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', 'offer')
            ->sole();

        $this->assertSame('نظام محتوى شهري يجلب طلبات', $row->value_json['value']);
        $this->assertSame('founder-interview', $row->value_json['source_tool']);
        $this->assertSame('user_confirmed', $row->value_json['provenance']);

        // الحقل الفارغ لا يُحفظ.
        $this->assertDatabaseMissing('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'market',
        ]);
    }

    #[Test]
    public function saved_interview_values_prefill_a_downstream_tool(): void
    {
        [$owner, $workspace, $project] = $this->scenario();

        // مقابلة تملأ العرض والعميل المثالي.
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('interview.store'), [
                'project_id' => $project->id,
                'answers' => [
                    'offer' => 'نظام محتوى شهري يجلب طلبات',
                    'ideal_customer' => 'أصحاب المطاعم الصغيرة في الخرطوم',
                ],
            ])
            ->assertSessionHas('status');

        // أداة التسعير تستهلك canonical 'offer' في حقل pricing_offer (خريطة CONSUMES).
        $tool = Tool::query()->updateOrCreate(
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

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson(route('api.tools.load', $tool).'?project_id='.$project->id);

        $response
            ->assertOk()
            ->assertJsonPath('experience.modes.guided.fields.pricing_offer.suggested_value', 'نظام محتوى شهري يجلب طلبات')
            ->assertJsonPath('experience.modes.guided.fields.pricing_offer.suggestion_source', 'derived_from_tool');
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
            'name' => 'Interview Account',
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
            'name' => 'Interview Workspace',
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
            'name' => 'Interview Project',
            'stage' => 2,
            'status' => 'active',
        ]);

        return [$owner, $workspace, $project];
    }
}
