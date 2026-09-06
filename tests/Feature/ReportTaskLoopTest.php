<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Task;
use App\Models\Tool;
use App\Models\User;
use App\Modules\Execution\MaterializeTasksFromReport;
use App\Services\Billing\CreditManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * B3 — إقفال الحلقة.
 *
 * ما تحرسه: ألّا يعود المنتج إلى الانتهاء عند النقطة التي تبدأ عندها
 * القيمة — ستة تقارير مليئة بالتوصيات وصفر مهام.
 */
class ReportTaskLoopTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function publishing_a_report_generates_its_plan_automatically(): void
    {
        $report = $this->publishedReportWithRecommendations(3);

        app(MaterializeTasksFromReport::class)->handle($report);

        $this->assertSame(3, Task::where('project_id', $report->project_id)->count());
    }

    /**
     * الحالة الابتدائية اقتراح لا التزام: أربع عشرة مهمة مفتوحة فجأة
     * تُنتج الهجر نفسه الذي ينتجه صفرُ مهام، من الطرف الآخر.
     */
    #[Test]
    public function generated_tasks_start_as_suggestions_not_commitments(): void
    {
        $report = $this->publishedReportWithRecommendations(2);

        app(MaterializeTasksFromReport::class)->handle($report);

        $this->assertSame(2, Task::where('status', Task::STATUS_SUGGESTED)->count());
        $this->assertSame(0, Task::where('status', Task::STATUS_TODO)->count());
    }

    #[Test]
    public function regenerating_the_same_report_never_duplicates_the_plan(): void
    {
        $report = $this->publishedReportWithRecommendations(3);

        app(MaterializeTasksFromReport::class)->handle($report);
        app(MaterializeTasksFromReport::class)->handle($report);
        app(MaterializeTasksFromReport::class)->handle($report);

        $this->assertSame(3, Task::count(), 'إعادة النشر ضاعفت الخطة.');
    }

    /**
     * ولا تُعاد مهمة أنجزها المستخدم إلى «مقترحة» عند إعادة النشر.
     */
    #[Test]
    public function regeneration_never_resets_work_the_user_already_did(): void
    {
        $report = $this->publishedReportWithRecommendations(1);
        app(MaterializeTasksFromReport::class)->handle($report);

        $task = Task::firstOrFail();
        $task->update(['status' => Task::STATUS_DONE, 'completed_at' => now()]);

        app(MaterializeTasksFromReport::class)->handle($report);

        $this->assertSame(Task::STATUS_DONE, $task->refresh()->status);
    }

    /**
     * التقرير غير المنشور لا يُنتج خطة: توصياته قد تتغير عند إعادة التحقق.
     */
    #[Test]
    public function a_draft_report_produces_no_plan(): void
    {
        $report = $this->publishedReportWithRecommendations(2);
        $report->forceFill(['status' => 'draft'])->save();

        app(MaterializeTasksFromReport::class)->handle($report->refresh());

        $this->assertSame(0, Task::count());
    }

    #[Test]
    public function the_user_adopts_a_suggestion_with_one_action(): void
    {
        $report = $this->publishedReportWithRecommendations(1);
        app(MaterializeTasksFromReport::class)->handle($report);

        $task = Task::firstOrFail();
        $owner = $report->project->workspace->owner;

        $this->actingAs($owner)
            ->post(route('app.tasks.adopt', $task))
            ->assertRedirect();

        $this->assertSame(Task::STATUS_TODO, $task->refresh()->status);
        $this->assertSame($owner->id, $task->owner_id);
    }

    #[Test]
    public function the_plan_page_shows_the_suggestions(): void
    {
        $report = $this->publishedReportWithRecommendations(2);
        app(MaterializeTasksFromReport::class)->handle($report);

        $this->actingAs($report->project->workspace->owner)
            ->get(route('app.plan'))
            ->assertOk()
            ->assertSeeText('مقترحة عليك')
            ->assertSeeText('أضِفها إلى خطتي');
    }

    private function publishedReportWithRecommendations(int $count): Report
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الحلقة']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        app(CreditManager::class)->walletFor($project->workspace)
            ->forceFill(['balance' => 500])->save();

        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير الحلقة',
            'locale' => 'ar',
            'status' => 'published',
            'score' => 55,
            'score_band' => 'forming',
        ]);

        for ($i = 1; $i <= $count; $i++) {
            // كل توصية تتبع فجوة: التوصية بلا فجوة نصيحة عامة لا مخرَج
            // تشخيص، والعمود إلزامي في المخطط لهذا السبب.
            $finding = Finding::create([
                'report_id' => $report->id,
                'category' => 'positioning',
                'title' => "فجوة {$i}",
                'description' => 'فجوة مرصودة.',
                'severity' => 'high',
                'sort_order' => $i,
            ]);

            Recommendation::create([
                'finding_id' => $finding->id,
                'report_id' => $report->id,
                'title' => "توصية {$i}",
                'description' => 'وصف قابل للتنفيذ.',
                'priority' => 100 - $i,
                'impact' => 'high',
                'effort' => 'low',
            ]);
        }

        return $report->refresh();
    }
}
