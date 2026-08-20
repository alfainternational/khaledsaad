<?php

namespace Tests\Feature;

use App\Jobs\DevelopTaskGuide;
use App\Models\AiUsageRecord;
use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Task;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Measurement\QueryBudgetManager;
use App\Notifications\TaskOverdueNotification;
use App\Notifications\TaskReminderNotification;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * من التوصية إلى مهمة منفَّذة: التحويل الجماعي، ونقل ما يجعلها قابلة
 * للتنفيذ، والتنبيه الذي يبقيها حيّة (§٤.٥).
 */
class TaskExecutionGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function converting_all_recommendations_leaves_none_behind(): void
    {
        $report = $this->report(recommendations: 5);
        $owner = $report->project->workspace->owner;

        $this->actingAs($owner)
            ->post(route('app.reports.convert', $report->id), ['scope' => 'all'])
            ->assertRedirect();

        // حدّ الثلاث كان قرار واجهة تسرّب إلى الخدمة.
        $this->assertSame(5, Task::where('project_id', $report->project_id)->count());
    }

    #[Test]
    public function converting_all_twice_does_not_duplicate_tasks(): void
    {
        $report = $this->report(recommendations: 3);
        $owner = $report->project->workspace->owner;

        $this->actingAs($owner)->post(route('app.reports.convert', $report->id), ['scope' => 'all']);
        $this->actingAs($owner)->post(route('app.reports.convert', $report->id), ['scope' => 'all']);

        $this->assertSame(3, Task::where('project_id', $report->project_id)->count());
    }

    #[Test]
    public function the_task_carries_the_steps_and_the_example_from_its_recommendation(): void
    {
        $report = $this->report(recommendations: 1);
        $recommendation = $report->recommendations()->firstOrFail();

        $task = app(ToolRunService::class)->convertRecommendation(
            $recommendation,
            $report->project->workspace->owner,
        );

        // المهمة التي تنسخ العنوان وحده تعيد المستخدم إلى نفس الحيرة.
        $this->assertSame($recommendation->action_steps, $task->steps);
        $this->assertSame($recommendation->worked_example, $task->worked_example);
        $this->assertNotNull($task->reminder_at);
        $this->assertTrue($task->reminder_at->lt($task->due_date->endOfDay()));
    }

    #[Test]
    public function developing_a_task_queues_the_job_and_marks_it_pending(): void
    {
        Queue::fake();

        $report = $this->report(recommendations: 1);
        $owner = $report->project->workspace->owner;
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $owner,
        );

        $this->actingAs($owner)
            ->post(route('app.tasks.develop', $task->id))
            ->assertRedirect();

        Queue::assertPushed(DevelopTaskGuide::class);
        $this->assertSame(Task::GUIDE_PENDING, $task->fresh()->guide_status);
    }

    #[Test]
    public function a_second_develop_request_while_pending_does_not_queue_again(): void
    {
        Queue::fake();

        $report = $this->report(recommendations: 1);
        $owner = $report->project->workspace->owner;
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $owner,
        );

        $this->actingAs($owner)->post(route('app.tasks.develop', $task->id));
        $this->actingAs($owner)->post(route('app.tasks.develop', $task->id));

        // الصرف المكرر على نفس النتيجة يخالف سقف التكلفة (§٤.٤).
        Queue::assertPushed(DevelopTaskGuide::class, 1);
    }

    /**
     * §٤.٤: الحجز قبل الطابور لا بعده. الحجز داخل المهمة يعني أنها دخلت
     * الطابور أصلًا، فيصير الرفض تعطيلًا متأخرًا لا منعًا.
     */
    #[Test]
    public function the_query_budget_is_reserved_before_the_job_enters_the_queue(): void
    {
        Queue::fake();

        $report = $this->report(recommendations: 1);
        $owner = $report->project->workspace->owner;
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $owner,
        );

        $budget = app(QueryBudgetManager::class)->budgetFor($report->project->workspace);
        $before = $budget->committed();

        $this->actingAs($owner)->post(route('app.tasks.develop', $task->id));

        $this->assertSame($before + 1, $budget->fresh()->committed());
    }

    /**
     * السقف نفد: لا يُرفض الطلب ولا يُستدعى نموذج — يُكتب دليل حتمي معلَّم
     * المصدر. الرفض يترك المستخدم بلا شيء، والادعاء يخفي الفرق (§٤.٣).
     */
    #[Test]
    public function an_exhausted_budget_still_produces_a_guide_without_calling_a_model(): void
    {
        $report = $this->report(recommendations: 1);
        $owner = $report->project->workspace->owner;
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $owner,
        );

        // السقف على المساحة لا على صفّ الشهر: الصفّ قيمة مشتقّة تُزامَن مع
        // الباقة عند كل قراءة، فكتابته مباشرة تُمحى عند أول استعلام.
        $report->project->workspace->forceFill(['monthly_query_limit' => 0])->save();

        $this->actingAs($owner)->post(route('app.tasks.develop', $task->id));

        $task->refresh();

        $this->assertSame(Task::GUIDE_FALLBACK, $task->guide_status);
        $this->assertNotEmpty($task->guide['examples'][0]['body'] ?? '');
        // ولا استدعاء واحد خارج السقف.
        $this->assertSame(0, AiUsageRecord::where('stage', 'task_guide')->count());
    }

    #[Test]
    public function a_stranger_cannot_develop_someone_elses_task(): void
    {
        $report = $this->report(recommendations: 1);
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $report->project->workspace->owner,
        );

        $this->actingAs(User::factory()->create())
            ->post(route('app.tasks.develop', $task->id))
            ->assertNotFound();
    }

    #[Test]
    public function the_reminder_fires_once_before_the_deadline(): void
    {
        Notification::fake();

        $report = $this->report(recommendations: 1);
        $owner = $report->project->workspace->owner;
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $owner,
        );

        $task->forceFill(['reminder_at' => now()->subHour()])->save();

        $this->artisan('tasks:remind')->assertSuccessful();
        $this->artisan('tasks:remind')->assertSuccessful();

        Notification::assertSentToTimes($owner, TaskReminderNotification::class, 1);
    }

    #[Test]
    public function an_overdue_task_notifies_its_owner(): void
    {
        Notification::fake();

        $report = $this->report(recommendations: 1);
        $owner = $report->project->workspace->owner;
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $owner,
        );

        $task->forceFill(['due_date' => now()->subDay()])->save();

        $this->artisan('tasks:remind')->assertSuccessful();

        Notification::assertSentTo($owner, TaskOverdueNotification::class);
        // المتأخرة لا تُلاحَق بتذكير مسبق بعد اليوم.
        $this->assertNull($task->fresh()->reminder_at);
    }

    #[Test]
    public function a_completed_task_is_never_chased(): void
    {
        Notification::fake();

        $report = $this->report(recommendations: 1);
        $owner = $report->project->workspace->owner;
        $task = app(ToolRunService::class)->convertRecommendation(
            $report->recommendations()->firstOrFail(),
            $owner,
        );

        $task->forceFill([
            'status' => Task::STATUS_DONE,
            'due_date' => now()->subDay(),
            'reminder_at' => now()->subDays(2),
        ])->save();

        $this->artisan('tasks:remind')->assertSuccessful();

        Notification::assertNothingSentTo($owner);
    }

    private function report(int $recommendations): Report
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع التنفيذ']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 55])->save();

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير التنفيذ',
            'status' => 'published',
            'score' => 55,
            'score_band' => 'مستقر',
            'summary' => 'ملخص يكفي للعرض في الاختبار.',
            'next_step' => ['title' => 'ابدأ هنا', 'description' => 'خطوة أولى واضحة.'],
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'التواصل',
            'title' => 'لا مسار واضح للتواصل',
            'description' => 'كل محاولة تبدأ من الصفر.',
            'severity' => 'high',
            'evidence' => 'إجابة المستخدم',
            'confidence' => 80,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        for ($index = 0; $index < $recommendations; $index++) {
            Recommendation::create([
                'finding_id' => $finding->id,
                'report_id' => $report->id,
                'title' => "توصية رقم {$index}",
                'description' => 'وصف كافٍ لتوصية قابلة للتحويل إلى مهمة.',
                'action_steps' => ['افتح جدولًا وسجّل فيه الأسماء.', 'أرسل رسالة تعريف واحدة اليوم.'],
                'worked_example' => [
                    'kind' => 'message',
                    'kind_label' => 'رسالة جاهزة',
                    'title' => 'رسالة أولى',
                    'body' => 'السلام عليكم [الاسم]، معك [اسمك]. وصلني اسمك من [مصدر التعارف] وأحببت أسألك سؤالًا واحدًا.',
                    'notes' => [],
                    'source' => 'ai',
                    'evidence_level' => 'inferred',
                ],
                'timeframe' => 'خلال أسبوعين',
                'impact' => 'high',
                'effort' => 'low',
                'priority' => 90 - $index,
            ]);
        }

        return $report->fresh();
    }
}
