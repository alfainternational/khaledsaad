<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Project;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\CreditManager;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Billing\FeatureKey;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * الميزات عناصر حقيقية: ما لا تشمله الخطة يُمنع فعلًا، وما تشمله بعدد
 * يتوقف عند عدده. لا شيء من هذا يعتمد على إخفاء زر في الواجهة.
 */
class PlanFeatureEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function the_catalogue_covers_every_key_the_code_enforces(): void
    {
        // ميزة يسأل عنها الكود ولا وجود لها في الفهرس = بوابة مفتوحة صامتة.
        foreach (FeatureKey::all() as $key) {
            $this->assertDatabaseHas('features', ['key' => $key, 'enforcement' => 'gate']);
        }
    }

    #[Test]
    public function pdf_export_is_blocked_on_the_free_plan_and_allowed_on_a_plan_that_includes_it(): void
    {
        $report = $this->report();
        $owner = $report->project->workspace->owner;

        $this->actingAs($owner)
            ->get(route('app.reports.pdf', $report->id))
            ->assertRedirect(route('app.billing'))
            ->assertSessionHasErrors('feature');

        $this->upgrade($report->project->workspace, 'individual');

        $this->actingAs($owner)
            ->get(route('app.reports.pdf', $report->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function the_project_limit_comes_from_the_feature_element(): void
    {
        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();

        // المجانية: مشروع واحد.
        app(ProjectService::class)->create($user, ['name' => 'الأول']);

        $this->assertFalse(app(SubscriptionManager::class)->canCreateProject($workspace->fresh()));

        // رفع عدد العنصر وحده يرفع الحد — بلا لمس كود.
        $this->setFeatureValue('free', FeatureKey::PROJECTS_LIMIT, 3);
        app(Entitlements::class)->flush();

        $this->assertTrue(app(SubscriptionManager::class)->canCreateProject($workspace->fresh()));
    }

    #[Test]
    public function the_monthly_run_quota_stops_the_run_before_any_credit_is_held(): void
    {
        Queue::fake();

        $this->setFeatureValue('free', FeatureKey::TOOL_RUNS_MONTHLY, 1);
        app(Entitlements::class)->flush();

        $project = $this->project();
        $workspace = $project->workspace;
        app(CreditManager::class)->grant($workspace, 50, 'اختبار');

        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        app(ToolRunService::class)->queue($this->filledRun($project, $tool));

        $balanceAfterFirst = $workspace->wallet->fresh()->balance;

        try {
            app(ToolRunService::class)->queue($this->filledRun($project, $tool, fresh: true));
            $this->fail('كان يجب رفض التشغيل الثاني لتجاوز الحصة.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('حصة خطتك', $exception->getMessage());
        }

        // الحصة تُفحص قبل الحجز: لا رصيد يُمس في المحاولة المرفوضة.
        $this->assertSame($balanceAfterFirst, $workspace->wallet->fresh()->balance);
    }

    #[Test]
    public function the_competitor_limit_is_enforced_per_project(): void
    {
        $this->setFeatureValue('free', FeatureKey::COMPETITORS_LIMIT, 2);
        app(Entitlements::class)->flush();

        $project = $this->project();
        $owner = $project->workspace->owner;

        $this->actingAs($owner)
            ->post(route('app.competitors.store', $project), ['names' => 'منافس أول، منافس ثانٍ'])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('app.competitors.store', $project), ['names' => 'منافس ثالث'])
            ->assertSessionHasErrors('names');

        $this->assertSame(2, $project->competitors()->count());
    }

    #[Test]
    public function a_plan_with_no_elements_configured_stays_open(): void
    {
        // خطة قديمة لم يضبطها الآدمن بعد: لا ينقلب التفعيل إلى منع مفاجئ.
        $plan = Plan::create([
            'key' => 'legacy', 'name' => 'قديمة', 'price' => 0,
            'monthly_credits' => 5, 'project_limit' => 2, 'sort_order' => 90,
        ]);

        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();
        app(SubscriptionManager::class)->subscribe($workspace, $plan);
        app(Entitlements::class)->flush();

        $this->assertTrue(app(Entitlements::class)->allows($workspace, FeatureKey::REPORTS_PDF));
        $this->assertTrue(app(Entitlements::class)->allows($workspace, FeatureKey::AUDIENCE_LAB));
    }

    #[Test]
    public function the_admin_can_shape_a_plan_from_the_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::where('key', 'free')->firstOrFail();
        $pdf = Feature::where('key', FeatureKey::REPORTS_PDF)->firstOrFail();
        $projects = Feature::where('key', FeatureKey::PROJECTS_LIMIT)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'key' => $plan->key,
            'name' => $plan->name,
            'interval' => 'monthly',
            'price' => 0,
            'monthly_credits' => 5,
            'project_limit' => 1,
            'is_public' => 1,
            'features' => [
                $pdf->id => ['enabled' => 1],
                $projects->id => ['enabled' => 1, 'value' => 4],
            ],
        ])->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('plan_features', [
            'plan_id' => $plan->id, 'feature_id' => $pdf->id, 'enabled' => 1,
        ]);
        // العدد يسري على العمود القديم أيضًا فلا يتناقض مصدران.
        $this->assertSame(4, $plan->fresh()->project_limit);

        // ما لم يُختَر يسقط من الخطة، ولا يبقى صفًّا معلّقًا.
        $this->assertSame(2, $plan->planFeatures()->count());
    }

    #[Test]
    public function the_billing_admin_screens_render(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plan = Plan::where('key', 'professional')->firstOrFail();
        $feature = Feature::where('key', FeatureKey::REPORTS_PDF)->firstOrFail();
        $gateway = PaymentGateway::where('provider', 'paypal')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.features.index'))->assertOk()->assertSee('فهرس الميزات');
        $this->actingAs($admin)->get(route('admin.features.edit', $feature))->assertOk();
        $this->actingAs($admin)->get(route('admin.features.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.plans.edit', $plan))->assertOk()->assertSee('عناصر الميزات');
        $this->actingAs($admin)->get(route('admin.gateways.edit', $gateway))->assertOk()->assertSee('رابط الإشعار');
        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();
    }

    #[Test]
    public function the_billing_page_lists_the_plan_elements_not_free_text(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('app.billing'))
            ->assertOk()
            // نص العنصر يأتي من الفهرس بعدده، لا من سطر كتبه أحد يدويًا.
            ->assertSee('المشاريع — 10 مشروع')
            ->assertSee('تصدير PDF');
    }

    private function upgrade(Workspace $workspace, string $planKey): void
    {
        app(SubscriptionManager::class)->subscribe($workspace, Plan::where('key', $planKey)->firstOrFail());
        app(Entitlements::class)->flush();
    }

    private function setFeatureValue(string $planKey, string $featureKey, ?int $value): void
    {
        PlanFeature::updateOrCreate(
            [
                'plan_id' => Plan::where('key', $planKey)->value('id'),
                'feature_id' => Feature::where('key', $featureKey)->value('id'),
            ],
            ['enabled' => true, 'value' => $value],
        );
    }

    private function project(): Project
    {
        $user = User::factory()->create();

        return app(ProjectService::class)->create($user, ['name' => 'مشروع اختبار']);
    }

    private function filledRun(Project $project, Tool $tool, bool $fresh = false): ToolRun
    {
        $run = app(ToolRunService::class)->start($project, $tool, $project->workspace->owner, fresh: $fresh);

        $steps = [
            1 => ['business_model' => 'services', 'description' => str_repeat('وصف واضح ', 4), 'geography' => 'الرياض', 'monthly_budget' => 3000],
            2 => ['primary_goal' => 'leads', 'value_proposition' => 'نسلّم خلال 48 ساعة أو نعيد المبلغ كاملًا بلا أسئلة', 'audience_clarity' => 'documented'],
            3 => ['active_channels' => ['seo'], 'tracking_maturity' => 'full', 'content_cadence' => 'weekly'],
            4 => ['landing_experience' => 'optimized', 'retention_motion' => 'systematic', 'sales_cycle' => 'medium', 'known_cac' => 90],
        ];

        foreach ($steps as $step => $input) {
            app(ToolRunService::class)->saveStep($run, $step, $input);
        }

        return $run->refresh();
    }

    private function report(): Report
    {
        $project = $this->project();
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $project->workspace->owner);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 62])->save();

        return Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير اختبار',
            'status' => 'published',
            'score' => 62,
            'score_band' => 'مستقر',
            'summary' => 'ملخص تجريبي يكفي لعرض التقرير.',
            'next_step' => ['title' => 'ابدأ هنا', 'description' => 'خطوة أولى واضحة.'],
        ]);
    }
}
