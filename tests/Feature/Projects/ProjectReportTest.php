<?php

namespace Tests\Feature\Projects;

use App\Application\Reports\BuildProjectReportAction;
use App\Contracts\AiGatewayInterface;
use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Jobs\WarmProjectReportSynthesisJob;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function web_report_never_blocks_on_llm_and_warms_via_queue(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        Queue::fake();

        // بوّابة LLM يجب ألّا تُستدعى إطلاقاً على مسار الويب (allowBlocking=false).
        $gateway = Mockery::mock(AiGatewayInterface::class);
        $gateway->shouldReceive('generateText')->never();
        $this->app->instance(AiGatewayInterface::class, $gateway);

        ['user' => $user, 'project' => $project] = $this->makeWorkspaceProjectWithRun();

        $response = $this->actingAs($user)->get("/projects/{$project->id}/report");

        $response->assertOk();
        // كاش بارد → يُرسَل Job التدفئة للطابور بدل الحجب المتزامن.
        Queue::assertPushed(WarmProjectReportSynthesisJob::class, fn ($job) => $job->projectId === $project->id);
    }

    #[Test]
    public function blocking_path_generates_llm_synthesis_once_then_serves_from_cache(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        // عزل: نمنع أي Job جانبي (sync) ناتج عن تهيئة البيانات من استدعاء البوّابة.
        Queue::fake();

        // نعدّ استدعاءات LLM: التوليد الأول يُجريها ويُكاش، والقراءة التالية من الكاش
        // دون أي استدعاء إضافي (بصرف النظر عن عدد استدعاءات التوليد الداخلية).
        $calls = 0;
        $gateway = Mockery::mock(AiGatewayInterface::class);
        $gateway->shouldReceive('generateText')->andReturnUsing(function () use (&$calls) {
            $calls++;

            return '{"executive_summary":"ملخص تنفيذي مبني على المخرجات.",'
                .'"priorities":["أولوية 1"],'
                .'"plan":{"quick_wins_7":["إجراء سريع"],"improvements_30":[],"strategic_90":[]}}';
        });
        $this->app->instance(AiGatewayInterface::class, $gateway);

        ['project' => $project] = $this->makeWorkspaceProjectWithRun();

        // fresh=true, allowBlocking=true → يماثل ما ينفّذه WarmProjectReportSynthesisJob.
        $first = app(BuildProjectReportAction::class)->handle($project, fresh: true, allowBlocking: true);
        $this->assertSame('llm', $first['synthesis_source']);
        $this->assertStringContainsString('ملخص تنفيذي', $first['executive_summary']);
        $this->assertGreaterThan(0, $calls, 'المسار المحجوب يجب أن يستدعي LLM ويُكاش الناتج');
        $callsAfterFirst = $calls;

        // قراءة لاحقة بلا fresh → من الكاش، بلا أي استدعاء LLM إضافي.
        $second = app(BuildProjectReportAction::class)->handle($project, fresh: false, allowBlocking: true);
        $this->assertSame('llm', $second['synthesis_source']);
        $this->assertSame($first['executive_summary'], $second['executive_summary']);
        $this->assertSame($callsAfterFirst, $calls, 'القراءة الثانية يجب أن تأتي من الكاش بلا استدعاء LLM');
    }

    /**
     * @return array{user: User, workspace: Workspace, project: Project}
     */
    private function makeWorkspaceProjectWithRun(): array
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Report Account',
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
            'name' => 'Report Workspace',
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
            'name' => 'Report Project',
            'stage' => 2,
            'status' => 'active',
        ]);

        // تشغيل أداة واحد على الأقل حتى لا يسلك التقرير مسار «لا أدوات منجَزة».
        ToolRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'tool_code' => 'diagnosis',
            'mode' => 'guided',
            'inputs_json' => ['q' => 'answer'],
            'output_json' => [],
            'summary_json' => ['headline' => 'تشخيص أولي', 'bullets' => ['نقطة']],
            'completeness_score' => 60,
            'created_by' => $user->id,
        ]);

        return ['user' => $user, 'workspace' => $workspace, 'project' => $project];
    }
}
