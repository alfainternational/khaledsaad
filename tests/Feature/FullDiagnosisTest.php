<?php

namespace Tests\Feature;

use App\Jobs\FinishFullDiagnosis;
use App\Jobs\RunToolPipeline;
use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Reporting\AgencyReportService;
use App\Services\Billing\CreditManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\FullDiagnosisRunner;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * أمر واحد يشغّل الأدوات كلها. القاعدة المعتمدة: نشغّل بما هو معروف،
 * ونُعلن الفراغ بدل أن نمنع أو نصمت.
 */
class FullDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function one_command_starts_every_runnable_tool_even_with_unanswered_questions(): void
    {
        Bus::fake();
        [$user, $project] = $this->project();

        $result = app(FullDiagnosisRunner::class)->run($project, $user);
        $expected = Tool::runnable()->count();

        $this->assertSame($expected, $result['started_count']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame($expected, $project->runs()->count());

        // لم يُمنع أي تشغيل رغم أن أغلب الأسئلة بلا إجابة.
        $this->assertSame(
            0,
            $project->runs()->where('status', ToolRun::STATUS_DRAFT)->count(),
        );

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === $expected
            && $batch->jobs->first() instanceof RunToolPipeline);
    }

    #[Test]
    public function what_the_platform_already_knows_is_carried_into_every_run(): void
    {
        Bus::fake();
        [$user, $project] = $this->project();

        ProjectAnswer::updateOrCreate(
            ['project_id' => $project->id, 'field_key' => 'monthly_budget'],
            ['value_json' => ['value' => '18000'], 'source_tool_key' => null],
        );

        app(FullDiagnosisRunner::class)->run($project, $user);

        $carried = ToolRun::whereIn('id', $project->runs()->pluck('id'))
            ->with('answers')
            ->get()
            ->filter(fn (ToolRun $run) => $run->answers->contains('field_key', 'monthly_budget'));

        // السؤال نفسه لا يُعاد طرحه في كل أداة تسأله.
        $this->assertGreaterThan(0, $carried->count());
    }

    #[Test]
    public function the_preview_warns_before_running_instead_of_blocking(): void
    {
        [, $project] = $this->project();

        $preview = app(FullDiagnosisRunner::class)->preview($project);

        $this->assertGreaterThan(0, $preview['tool_count']);
        $this->assertTrue($preview['needs_warning']);
        $this->assertStringContainsString('افتراضات مُعلنة', $preview['warning']);
        $this->assertLessThan(50, $preview['coverage_percent']);

        // الاستعراض لا يخلّف مسودّات وراءه.
        $this->assertSame(0, $project->runs()->count());
    }

    #[Test]
    public function the_unified_document_is_built_automatically_when_the_batch_finishes(): void
    {
        Bus::fake();
        [$user, $project] = $this->project();

        app(FullDiagnosisRunner::class)->run($project, $user);

        Bus::assertBatched(function ($batch) {
            // الدفعة تحمل خطوة الإنهاء التي تبني المستند بلا ضغطة ثانية.
            return $batch->name !== '' && $batch->thenCallbacks() !== [];
        });

        // وخطوة الإنهاء نفسها لا تنهار حين تكون الأدوات الأساسية ناقصة.
        (new FinishFullDiagnosis($project->id, $user->id))
            ->handle(app(AgencyReportService::class));

        $this->assertSame(0, $project->agencyReports()->count());
    }

    #[Test]
    public function the_manual_route_sends_every_run_to_human_review_without_queueing(): void
    {
        Bus::fake();
        [$user, $project] = $this->project();

        $result = app(FullDiagnosisRunner::class)
            ->run($project, $user, FullDiagnosisRunner::MODE_MANUAL);

        $this->assertSame(FullDiagnosisRunner::MODE_MANUAL, $result['mode']);
        $this->assertGreaterThan(0, $result['started_count']);
        $this->assertSame(
            $result['started_count'],
            $project->runs()->where('delivery_mode', 'manual')->count(),
        );

        Bus::assertNothingBatched();
    }

    #[Test]
    public function the_owner_can_trigger_it_from_the_panel_and_a_stranger_cannot(): void
    {
        Bus::fake();
        [$user, $project] = $this->project();

        $this->actingAs($user)
            ->post(route('app.projects.full-diagnosis', $project), ['mode' => 'auto'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertGreaterThan(0, $project->runs()->count());

        $this->actingAs(User::factory()->create())
            ->post(route('app.projects.full-diagnosis', $project))
            ->assertNotFound();
    }

    #[Test]
    public function running_out_of_credit_stops_that_tool_alone_and_states_why(): void
    {
        Bus::fake();
        [$user, $project] = $this->project();

        // رصيد صفر: لا أداة مدفوعة تُشغَّل، والسبب يُعلن لكل واحدة.
        $project->workspace->wallet()->update(['balance' => 0]);

        $result = app(FullDiagnosisRunner::class)->run($project->fresh(), $user);

        $this->assertGreaterThan(0, $result['skipped_count']);
        $this->assertStringContainsString('رصيد', $result['skipped'][0]['reason']);
        $this->assertSame(
            Tool::runnable()->count(),
            $result['started_count'] + $result['skipped_count'],
        );
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع التشخيص الشامل',
            'industry' => 'التجارة الإلكترونية',
        ]);

        // رصيد يكفي تشغيل الأدوات كلها؛ نفادُه حالة مستقلة تختبرها دالة أخرى.
        app(CreditManager::class)->grant($project->workspace, 500, 'اختبار');

        return [$user, $project];
    }
}
