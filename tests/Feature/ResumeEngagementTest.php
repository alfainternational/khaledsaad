<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * القاعدة: من بدأ عملًا لا يُعرض عليه «ابدأ من هنا» كأن شيئًا لم يحدث،
 * ولا بد أن يجد طريقًا واضحًا للعودة إلى ما تركه.
 */
class ResumeEngagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function an_untouched_tool_invites_the_user_to_start(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.tools.index'))
            ->assertOk()
            ->assertSee('اعرف التفاصيل وابدأ')
            ->assertDontSee('أكمل من حيث وقفت');
    }

    #[Test]
    public function a_started_tool_offers_to_continue_instead_of_starting_over(): void
    {
        [$user, $run] = $this->startedRun();

        $this->actingAs($user)
            ->get(route('app.tools.index'))
            ->assertOk()
            ->assertSee('أكمل من حيث وقفت')
            ->assertSee(route('app.runs.review', $run->uuid), false);
    }

    #[Test]
    public function the_dashboard_lists_unfinished_work_with_a_way_back(): void
    {
        [$user, $run] = $this->startedRun();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('أكمل ما بدأته')
            ->assertSee(route('app.runs.review', $run->uuid), false);
    }

    #[Test]
    public function a_running_analysis_points_back_to_its_status_page(): void
    {
        [$user, $run] = $this->startedRun();
        $run->forceFill(['status' => ToolRun::STATUS_PROCESSING])->save();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('تابع التحليل الجاري')
            ->assertSee(route('app.runs.status', $run->uuid), false);
    }

    #[Test]
    public function resuming_returns_the_same_run_but_starting_over_creates_a_new_one(): void
    {
        [$user, $run] = $this->startedRun();
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $project = $run->project;

        $resumed = app(ToolRunService::class)->start($project, $tool, $user);
        $this->assertSame($run->id, $resumed->id, 'الاستئناف يجب أن يعيد نفس المسودة.');

        $restarted = app(ToolRunService::class)->start($project, $tool, $user, fresh: true);
        $this->assertNotSame($run->id, $restarted->id, '«ابدأ من جديد» يجب أن ينشئ تشغيلًا جديدًا.');
        $this->assertNull(ToolRun::find($run->id), 'المسودة القديمة لا تبقى معلقة بلا معنى.');
    }

    #[Test]
    public function the_api_exposes_the_same_unfinished_work_as_the_web(): void
    {
        [$user, $run] = $this->startedRun();

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.engagements.unfinished'))
            ->assertOk()
            ->assertJsonPath('data.0.run_uuid', $run->uuid)
            ->assertJsonPath('data.0.state', 'draft')
            ->assertJsonPath('data.0.label', 'أكمل من حيث وقفت');
    }

    #[Test]
    public function the_api_reports_tool_state_inside_a_project(): void
    {
        [$user, $run] = $this->startedRun();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.engagements.project-tools', $run->project->slug))
            ->assertOk()
            ->json('data');

        $started = collect($response)->firstWhere('key', 'marketing-score');
        $untouched = collect($response)->firstWhere('key', 'content-engine');

        $this->assertSame('draft', $started['engagement']['state']);
        $this->assertSame('new', $untouched['engagement']['state']);
    }

    /**
     * @return array{0: User, 1: ToolRun}
     */
    private function startedRun(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الاستئناف']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        $run = app(ToolRunService::class)->start($project, $tool, $user);

        app(ToolRunService::class)->saveStep($run, 1, [
            'business_model' => 'services',
            'description' => str_repeat('وصف واضح للخدمة ', 3),
            'geography' => 'الرياض',
            'monthly_budget' => 4000,
        ]);

        return [$user, $run->refresh()];
    }
}
