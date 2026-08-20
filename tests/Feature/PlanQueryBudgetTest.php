<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionAssignmentService;
use App\Services\Billing\SubscriptionManager;
use App\Support\Billing\FeatureKey;
use App\Modules\Measurement\QueryBudgetManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ميزانية الاستعلامات صارت مزية باقة، لا رقمًا عامًّا واحدًا للجميع.
 *
 * قبل هذا كان `limitFor` يقرأ عمودًا **لا يضبطه شيء في المنصة**، فكل المساحات
 * على الافتراضي نفسه: الترقية ترفع الاسم ولا ترفع السقف. وهو الوجه الثاني
 * لعطل «الباقة تصل بلا مزاياها».
 */
class PlanQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** دورة استطلاع واحدة: ٥ أسئلة × ٣ محاولات (§٤.٢). وحدة قياس السخاء. */
    private const QUERIES_PER_SURVEY = 15;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function planBudgets(): array
    {
        return [
            'free' => ['free', 150],
            'individual' => ['individual', 600],
            'professional' => ['professional', 2000],
            'team' => ['team', 6000],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('planBudgets')]
    public function each_plan_carries_its_own_query_budget(string $planKey, int $expected): void
    {
        $workspace = $this->workspaceOn($planKey);

        $this->assertSame(
            $expected,
            app(QueryBudgetManager::class)->budgetFor($workspace)->monthly_limit,
            "سقف الاستعلامات لباقة {$planKey} لا يطابق المعتمد.",
        );
    }

    #[Test]
    public function the_budget_rises_with_the_plan_not_with_the_price_alone(): void
    {
        $budgets = [];

        foreach (array_keys(self::planBudgets()) as $planKey) {
            $budgets[] = app(QueryBudgetManager::class)
                ->budgetFor($this->workspaceOn($planKey))->monthly_limit;
        }

        $sorted = $budgets;
        sort($sorted);

        // السقف يتصاعد مع الباقة صعودًا مطّردًا: باقة أغلى بسقف أدنى تناقض.
        $this->assertSame($sorted, $budgets);
        $this->assertSame(count($budgets), count(array_unique($budgets)));
    }

    #[Test]
    public function the_free_plan_is_generous_enough_to_prove_the_value(): void
    {
        $workspace = $this->workspaceOn('free');
        $limit = app(QueryBudgetManager::class)->budgetFor($workspace)->monthly_limit;

        /*
         * المستوى ٠ يخلق الفجوة المعرفية ولا يقفلها (§٦). ومجّانيّ لا يكفي
         * سقفه لدورة استطلاع واحدة لا يرى الفجوة أصلًا، فلا يترقّى.
         */
        $this->assertGreaterThanOrEqual(
            self::QUERIES_PER_SURVEY * 10,
            $limit,
            'المجانية أضيق من أن تُظهر القيمة: عشر دورات استطلاع هي الحد الأدنى للسخاء.',
        );
    }

    #[Test]
    public function an_upgrade_raises_the_ceiling_in_the_same_month(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $workspace = $this->workspaceOn('free');
        $budgets = app(QueryBudgetManager::class);

        // الشهر بدأ فعلًا: الصفّ موجود بسقف المجانية.
        $budgets->reserve($workspace, 10, 'answer_presence');
        $this->assertSame(150, $budgets->budgetFor($workspace)->monthly_limit);

        app(SubscriptionAssignmentService::class)->assign(
            [$workspace->id], Plan::where('key', 'professional')->firstOrFail(), $admin,
        );
        app(\App\Services\Billing\Entitlements::class)->flush();

        $budget = $budgets->budgetFor($workspace->fresh());

        // السقف يرتفع فورًا، والمستهلك لا يُمحى — ترقية لا مسحًا للتاريخ.
        $this->assertSame(2000, $budget->monthly_limit);
        $this->assertSame(10, $budget->reserved);
        $this->assertSame(1990, $budget->remaining());
    }

    #[Test]
    public function a_workspace_override_still_beats_the_plan(): void
    {
        $workspace = $this->workspaceOn('free');
        $workspace->forceFill(['monthly_query_limit' => 5000])->save();

        // اتفاق خاص لمساحة بعينها قرارٌ صريح، والباقة قاعدة عامة.
        $this->assertSame(
            5000,
            app(QueryBudgetManager::class)->budgetFor($workspace->fresh())->monthly_limit,
        );
    }

    #[Test]
    public function a_zero_override_is_a_kill_switch_not_an_empty_field(): void
    {
        $workspace = $this->workspaceOn('team');
        $workspace->forceFill(['monthly_query_limit' => 0])->save();

        /*
         * صفرٌ مكتوب على المساحة قرارُ إيقاف صريح — يعلو على باقة الفرق
         * نفسها. لو قُرئ «فراغًا» لانفتح السقف على من أُوقف عمدًا.
         */
        $this->assertSame(
            0,
            app(QueryBudgetManager::class)->budgetFor($workspace->fresh())->monthly_limit,
        );
    }

    #[Test]
    public function a_plan_that_zeroes_the_element_falls_back_instead_of_stopping_everything(): void
    {
        $workspace = $this->workspaceOn('professional');
        $plan = $workspace->subscription->plan;
        $featureId = \App\Models\Feature::where('key', FeatureKey::QUERY_BUDGET_MONTHLY)->value('id');

        \App\Models\PlanFeature::where('plan_id', $plan->id)
            ->where('feature_id', $featureId)
            ->update(['enabled' => false]);
        app(\App\Services\Billing\Entitlements::class)->flush();

        /*
         * ميزة مغلقة تعطي صفرًا عبر Entitlements، والصفر يوقف كل استعلام.
         * منع الذكاء كليًّا عن باقة مدفوعة قرارٌ لا يُتخذ بخانة غير مؤشَّرة.
         */
        $this->assertSame(
            (int) config('growth.query_budget_default'),
            app(QueryBudgetManager::class)->budgetFor($workspace->fresh())->monthly_limit,
        );
    }

    #[Test]
    public function the_budget_is_shown_to_the_customer_as_a_plan_element(): void
    {
        $plan = Plan::where('key', 'professional')->firstOrFail();
        $labels = app(\App\Services\Billing\Entitlements::class)->displayFeatures($plan);

        $this->assertNotEmpty(
            array_filter($labels, fn (string $label) => str_contains($label, '2000')),
            'السقف لا يظهر للعميل ضمن عناصر الباقة: مزية لا تُرى لا تُشترى.',
        );
    }

    private function workspaceOn(string $planKey): Workspace
    {
        $workspace = User::factory()->create()->primaryWorkspace();

        app(SubscriptionManager::class)->subscribe(
            $workspace,
            Plan::where('key', $planKey)->firstOrFail(),
        );
        app(\App\Services\Billing\Entitlements::class)->flush();

        return $workspace->fresh();
    }
}
