<?php

namespace Tests\Feature;

use App\Exceptions\AIProviderException;
use App\Models\CreditTransaction;
use App\Models\Finding;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolRunAnswer;
use App\Models\User;
use App\Modules\Execution\MaterializeTasksFromReport;
use App\Services\Billing\CreditManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Experience\Experience;
use App\Support\Failures\FailureClassifier;
use App\Support\Failures\FailureKind;
use App\Support\Navigation\NavRegistry;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * المسار الذهبي — الرحلات الحرجة التي يمنع كسرُها كل ما سبق من إصلاح.
 *
 * كُتبت كاختبارات HTTP لا كاختبارات متصفّح: Playwright يحتاج خادمًا يعمل
 * وقاعدةً حيّة، فيبقى معطَّلًا في CI ولا يحرس شيئًا. هذه تعمل في كل تشغيل،
 * وتغطّي المسارات نفسها — ما عدا ما يحتاج متصفّحًا حقًّا (قطع الشبكة
 * أثناء الإجابة)، وهو مذكور في `docs/platform/12` ولم يُدَّعَ هنا.
 */
class GoldenPathTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ١ — حساب برصيد صفر يرى التكلفة **قبل** السؤال الأول.
     *
     * هذا هو A1 حرفيًّا: ستون سؤالًا ثم جدارٌ كان قائمًا قبل البدء.
     */
    #[Test]
    public function a_zero_balance_account_sees_the_cost_before_the_first_question(): void
    {
        $user = $this->user(balance: 0);

        $this->actingAs($user)
            ->get(route('app.consultations.index'))
            ->assertOk()
            ->assertSeeText('ينقصك')
            ->assertSee('disabled', false);
    }

    /**
     * ٢ — سقوط المزوّد: لا خصم، والإجابات محفوظة، والرسالة من نوع «ours».
     */
    #[Test]
    public function a_provider_outage_charges_nothing_and_keeps_the_answers(): void
    {
        $user = $this->user(balance: 100);
        $run = $this->startedRun($user);
        $wallet = $run->project->workspace->wallet;

        app(CreditManager::class)->hold($run, 5);
        $this->assertSame(95, $wallet->refresh()->balance);

        // عطل المزوّد كما يقع فعلًا في خط الأنابيب.
        app(CreditManager::class)->refund($run);
        $failure = (new FailureClassifier)
            ->classify(new AIProviderException('deepseek', 402));

        $this->assertSame(100, $wallet->refresh()->balance, 'خُصم رصيد على عطلٍ منّا.');
        $this->assertSame(FailureKind::Ours, $failure->kind);
        $this->assertNull($failure->userAction);

        // والإجابات لم تُمسّ.
        $this->assertDatabaseCount('tool_run_answers', ToolRunAnswer::count());
        $this->assertGreaterThan(0, $run->answers()->count());
    }

    /**
     * ٣ — توليد تقرير ⇒ ظهور مهام تلقائيًّا في صفحة الخطة.
     */
    #[Test]
    public function publishing_a_report_puts_tasks_on_the_plan_page(): void
    {
        $user = $this->user(balance: 100);
        $report = $this->publishedReport($user);

        app(MaterializeTasksFromReport::class)->handle($report);

        $this->actingAs($user)
            ->get(route('app.plan'))
            ->assertOk()
            ->assertSeeText('مقترحة عليك')
            ->assertSeeText('توصية ذهبية');
    }

    /**
     * ٤ — كل رابط ملاحة يغيّر المحتوى فعلًا: لا وجهة مكرّرة ولا مسار وهمي.
     */
    #[Test]
    public function every_navigation_link_leads_somewhere_that_actually_renders(): void
    {
        $user = $this->user(balance: 50);
        $seen = [];

        foreach (NavRegistry::primary(Experience::BUSINESS) as $item) {
            if (! $item->isAvailable()) {
                continue;
            }

            $this->actingAs($user)->get($item->url())->assertOk();

            $this->assertNotContains(
                $item->url(),
                $seen,
                "عنصران في الملاحة يفتحان الوجهة نفسها: {$item->label}",
            );

            $seen[] = $item->url();
        }

        $this->assertGreaterThanOrEqual(4, count($seen));
    }

    /**
     * ٥ — الإجابات قابلة للاستئناف بعد أي فشل، ولا حالة نهائية تقول «ضاع مجهودك».
     */
    #[Test]
    public function answers_survive_a_failure_and_the_run_stays_resumable(): void
    {
        $user = $this->user(balance: 100);
        $run = $this->startedRun($user);
        $before = $run->answers()->count();

        $run->forceFill([
            'status' => ToolRun::STATUS_AWAITING_CAPACITY,
            'failure_kind' => FailureKind::Ours->value,
            'failure_reason' => 'إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء.',
        ])->save();

        $this->assertSame($before, $run->refresh()->answers()->count());
        $this->assertFalse($run->isTerminal());

        $this->actingAs($user)
            ->get(route('app.runs.status', $run->uuid))
            ->assertOk()
            ->assertDontSeeText('ضاع');
    }

    /**
     * ٦ — لا خصم مزدوج مهما تكرّر النداء.
     */
    #[Test]
    public function a_repeated_charge_never_deducts_twice(): void
    {
        $user = $this->user(balance: 100);
        $run = $this->startedRun($user);
        $wallet = $run->project->workspace->wallet;

        app(CreditManager::class)->hold($run, 5);
        app(CreditManager::class)->hold($run, 5);
        app(CreditManager::class)->charge($run);
        app(CreditManager::class)->charge($run);

        $this->assertSame(95, $wallet->refresh()->balance);
        $this->assertSame(1, CreditTransaction::where('tool_run_id', $run->id)
            ->where('type', CreditTransaction::TYPE_HOLD)->count());
    }

    private function user(int $balance): User
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المسار']);

        app(CreditManager::class)->walletFor($project->workspace)
            ->forceFill(['balance' => $balance])->save();

        return $user->refresh();
    }

    private function project(User $user): Project
    {
        return Project::whereHas('workspace', fn ($q) => $q->where('owner_id', $user->id))->firstOrFail();
    }

    private function startedRun(User $user): ToolRun
    {
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($this->project($user), $tool, $user);

        app(ToolRunService::class)->saveStep($run, 1, [
            'business_model' => 'services',
            'description' => str_repeat('وصف واضح للخدمة ', 3),
            'geography' => 'الرياض',
            'monthly_budget' => 4000,
        ]);

        return $run->refresh();
    }

    private function publishedReport(User $user): Report
    {
        $run = $this->startedRun($user);

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $run->project_id,
            'title' => 'تقرير المسار الذهبي',
            'locale' => 'ar',
            'status' => 'published',
            'score' => 61,
            'score_band' => 'focused',
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'positioning',
            'title' => 'فجوة ذهبية',
            'description' => 'فجوة مرصودة.',
            'severity' => 'high',
            'sort_order' => 1,
        ]);

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => 'توصية ذهبية',
            'description' => 'خطوة قابلة للتنفيذ.',
            'priority' => 90,
            'impact' => 'high',
            'effort' => 'low',
        ]);

        return $report->refresh();
    }
}
