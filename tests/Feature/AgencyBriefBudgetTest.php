<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Marketing\BudgetPlanner;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportService;
use App\Services\Reports\AgencyReportSharing;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الميزانية الواحدة تعني شيئين، والخلط بينهما يَعِد بما لا يتحقق.
 *
 * كل اختبار هنا يمثّل حالة كان المستخدم يخرج منها برقم مطمئن وخاطئ:
 * 1000 شهريًا «شاملة» لوكالة بنطاق كامل لا تصل منها ريالات إلى الإعلان،
 * ومع ذلك كانت طاقة الاكتساب تُحسب كأن الألف كلها إعلان.
 */
class AgencyBriefBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function an_inclusive_budget_that_cannot_cover_the_fee_is_called_insufficient(): void
    {
        $plan = app(BudgetPlanner::class)->plan(
            monthlyBudget: 1000,
            geography: 'السعودية',
            services: ['strategy', 'ads', 'content', 'design', 'social', 'seo'],
            includesAgencyFee: true,
        );

        $this->assertSame('full_service', $plan['tier']['key']);
        $this->assertSame(BudgetPlanner::VERDICT_INSUFFICIENT, $plan['verdict']['level']);
        // لا يصل شيء للإعلان: الأتعاب وحدها تبتلع المبلغ.
        $this->assertSame(0.0, (float) $plan['effective_media']);
        $this->assertGreaterThan(0, $plan['verdict']['gap']);
    }

    #[Test]
    public function the_same_amount_declared_as_media_only_states_the_real_total_cost(): void
    {
        $plan = app(BudgetPlanner::class)->plan(
            monthlyBudget: 1000,
            geography: 'السعودية',
            services: ['ads'],
            includesAgencyFee: false,
        );

        // المبلغ كله وسائط، لكن التكلفة الحقيقية تشمل الأتعاب فوقه.
        $this->assertSame(1000.0, (float) $plan['effective_media']);
        $this->assertGreaterThan(1000, $plan['breakdown']['total_cost_of_ownership']);
    }

    #[Test]
    public function an_undeclared_budget_refuses_to_assume_and_shows_both_readings(): void
    {
        $plan = app(BudgetPlanner::class)->plan(
            monthlyBudget: 1000,
            geography: 'السعودية',
            services: ['ads'],
            includesAgencyFee: null,
        );

        $this->assertSame(BudgetPlanner::VERDICT_UNKNOWN, $plan['verdict']['level']);
        $this->assertNull($plan['effective_media']);
        $this->assertArrayHasKey('if_inclusive_media', $plan['breakdown']);
        $this->assertArrayHasKey('if_media_only_total', $plan['breakdown']);
    }

    #[Test]
    public function each_market_is_priced_in_its_own_currency_and_scale(): void
    {
        $planner = app(BudgetPlanner::class);

        $saudi = $planner->plan(5000, 'الرياض', ['ads'], true);
        $sudan = $planner->plan(5000, 'الخرطوم', ['ads'], true);

        $this->assertSame('SAR', $saudi['market']['currency']);
        $this->assertSame('USD', $sudan['market']['currency']);
        // نفس الرقم يعني قدرة مختلفة تمامًا باختلاف السوق.
        $this->assertNotSame(
            $saudi['reference']['agency_fee']['min'],
            $sudan['reference']['agency_fee']['min'],
        );
        $this->assertNotEmpty($sudan['market']['notes']);
    }

    #[Test]
    public function a_scope_of_one_service_is_not_priced_as_a_full_service_agency(): void
    {
        $planner = app(BudgetPlanner::class);

        $narrow = $planner->plan(4000, 'الرياض', ['analytics'], true);
        $wide = $planner->plan(4000, 'الرياض', ['strategy', 'ads', 'content', 'design', 'social', 'seo'], true);

        $this->assertSame('freelancer', $narrow['tier']['key']);
        $this->assertSame('full_service', $wide['tier']['key']);
        $this->assertGreaterThan(
            $narrow['reference']['agency_fee']['min'],
            $wide['reference']['agency_fee']['min'],
        );
    }

    #[Test]
    public function the_brief_is_saved_and_drives_the_document(): void
    {
        [$user, $project] = $this->project();

        $this->actingAs($user)->post(route('app.projects.agency-reports.brief', $project), [
            'brief' => [
                'services' => ['ads', 'content', 'social', 'design'],
                'primary_goal' => 'sales',
                'budget_includes_agency_fee' => 'yes',
                'budget_currency' => 'SAR',
                'success_metric' => '70 متجرًا يرفع أول منتج خلال 30 يومًا.',
                'account_ownership' => 'mine',
                'proposal_deadline' => '15 أغسطس 2026',
                'previous_attempts' => 'أعلنّا شهرين بلا قياس.',
                'decision_maker' => 'أنا شخصيًا.',
            ],
        ])->assertRedirect();

        $profile = $project->fresh()->profile;
        $this->assertTrue($profile->budget_includes_agency_fee);
        $this->assertContains('ads', $profile->agency_services);
        $this->assertSame('70 متجرًا يرفع أول منتج خلال 30 يومًا.', $profile->brief('success_metric'));

        // الخدمات لا تُكرَّر داخل الـJSON بعد ترحيلها إلى عمودها.
        $this->assertArrayNotHasKey('services', $profile->brief);

        $completeness = app(AgencyReportService::class)->briefCompleteness($project->fresh());
        $this->assertTrue($completeness['is_quotable']);
    }

    #[Test]
    public function a_brief_missing_a_critical_answer_is_not_quotable(): void
    {
        [$user, $project] = $this->project();

        $this->actingAs($user)->post(route('app.projects.agency-reports.brief', $project), [
            'brief' => ['services' => ['ads'], 'decision_maker' => 'أنا'],
        ]);

        $completeness = app(AgencyReportService::class)->briefCompleteness($project->fresh());

        $this->assertFalse($completeness['is_quotable']);
        $this->assertNotEmpty($completeness['missing_critical']);
    }

    #[Test]
    public function the_owner_guide_never_reaches_the_agency(): void
    {
        [$user, $project] = $this->project();

        app(AgencyReportService::class)->saveBrief($project, [
            'services' => ['ads'],
            'primary_goal' => 'sales',
            'success_metric' => '20 عملية شراء مدفوعة خلال 90 يومًا.',
            'budget_includes_agency_fee' => 'yes',
            'budget_currency' => 'SAR',
            'account_ownership' => 'mine',
            'proposal_deadline' => '15 أغسطس 2026',
        ]);

        foreach (['marketing-score', 'brand-clarity', 'audience-map'] as $key) {
            $this->publishedReport($project, $user, $key);
        }

        $report = app(AgencyReportService::class)->generate($project->fresh(), $user);

        // اللقطة تحفظه (سجل ثابت لصاحبه)…
        $this->assertNotEmpty($report->snapshot['owner_guide']['comparison_questions']);

        // …وصفحته يراها المالك…
        $this->actingAs($user)
            ->get(route('app.agency-reports.show', $report))
            ->assertOk()
            ->assertSee('أسئلة مقارنة عروض الوكالات')
            ->assertSee('علامات إنذار في العروض');

        // …ولا تراها الوكالة عبر الرابط المشترك.
        app(AgencyReportSharing::class)->share($report, 30);

        $this->get(route('shared.agency-report', $report->fresh()->share_token))
            ->assertOk()
            ->assertSee('ما يجب أن يتضمنه عرضكم')
            ->assertDontSee('أسئلة مقارنة عروض الوكالات')
            ->assertDontSee('علامات إنذار في العروض');
    }

    private function publishedReport(Project $project, User $user, string $key): void
    {
        $tool = Tool::where('key', $key)->firstOrFail();
        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user->id,
            'status' => ToolRun::STATUS_COMPLETED,
            'base_score' => 65,
        ]);

        Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => "تقرير {$tool->title}",
            'status' => 'published',
            'score' => 65,
            'score_band' => Report::bandFor(65),
            'summary' => 'ملخص.',
        ]);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'جيب لي',
            'geography' => 'السعودية',
            'monthly_budget' => 1000,
        ]);

        return [$user, $project];
    }
}
