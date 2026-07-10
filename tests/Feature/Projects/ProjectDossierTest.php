<?php

namespace Tests\Feature\Projects;

use App\Application\Reports\ProjectDossierBuilder;
use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectDossierTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_gathers_raw_answers_grouped_by_stage_with_human_labels(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        ['project' => $project] = $this->makeWorkspaceProject('diagnosis', [
            'main_problem' => 'ضعف الطلب رغم جودة الخدمة',
        ]);

        $dossier = app(ProjectDossierBuilder::class)->build($project);

        $this->assertTrue($dossier['has_answers']);
        $this->assertSame(1, $dossier['meta']['tools_completed']);
        $this->assertCount(5, $dossier['stages']);

        $stage1 = collect($dossier['stages'])->firstWhere('num', 1);
        $tool = collect($stage1['tools'])->firstWhere('code', 'diagnosis');
        $this->assertNotNull($tool);
        $answer = collect($tool['answers'])->first();
        $this->assertSame('ضعف الطلب رغم جودة الخدمة', $answer['value']);
        $this->assertNotSame('main_problem', $answer['label']); // عنوان بشري لا مفتاح خام

        // الوثيقة النصّية الموحّدة — ركيزة تحليل LLM في النظام الهجين.
        $this->assertStringContainsString('ضعف الطلب رغم جودة الخدمة', $dossier['markdown']);
        $this->assertStringContainsString('# دليل المشروع', $dossier['markdown']);
    }

    #[Test]
    public function multi_value_answers_flatten_and_blanks_drop(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        ['project' => $project] = $this->makeWorkspaceProject('diagnosis', [
            'channels' => ['انستغرام', 'واتساب'],
            'empty_field' => '   ',
        ]);

        $dossier = app(ProjectDossierBuilder::class)->build($project);
        $tool = collect(collect($dossier['stages'])->firstWhere('num', 1)['tools'])->firstWhere('code', 'diagnosis');
        $values = collect($tool['answers'])->pluck('value')->all();

        $this->assertContains('انستغرام، واتساب', $values);
        $this->assertNotContains('', $values);
    }

    #[Test]
    public function dossier_page_renders_the_raw_answers(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        ['user' => $user, 'project' => $project] = $this->makeWorkspaceProject('diagnosis', [
            'main_problem' => 'ضعف الطلب رغم جودة الخدمة',
        ]);

        $this->actingAs($user)
            ->get("/projects/{$project->id}/dossier")
            ->assertOk()
            ->assertSee('دليل المشروع')
            ->assertSee('ضعف الطلب رغم جودة الخدمة');
    }

    #[Test]
    public function dossier_page_is_forbidden_for_non_members(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        ['project' => $project] = $this->makeWorkspaceProject('diagnosis', ['main_problem' => 'خاص']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get("/projects/{$project->id}/dossier")
            ->assertNotFound(); // عزل المساحات: 404 لغير العضو
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{user: User, workspace: Workspace, project: Project}
     */
    private function makeWorkspaceProject(?string $toolCode, array $inputs): array
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Dossier Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        Subscription::query()->create(['account_id' => $account->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Dossier Workspace',
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
        app(OnboardingState::class)->markCompleted($workspace);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dossier Project',
            'stage' => 1,
            'status' => 'active',
        ]);

        if ($toolCode !== null) {
            ToolRun::query()->create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'tool_code' => $toolCode,
                'mode' => 'guided',
                'inputs_json' => $inputs,
                'output_json' => [],
                'summary_json' => ['headline' => 'خلاصة الأداة', 'bullets' => ['نقطة أولى']],
                'completeness_score' => 70,
                'created_by' => $user->id,
            ]);
        }

        return ['user' => $user, 'workspace' => $workspace, 'project' => $project->fresh()];
    }
}
