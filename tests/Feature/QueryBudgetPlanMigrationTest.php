<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Support\Billing\FeatureKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الهجرة تُضيف ولا تدهس.
 *
 * البيئة القائمة عليها باقات ضبطها الآدمن من اللوحة. هجرةٌ تكتب فوقها تُرجع
 * المنصة لافتراضات البذر بلا أن يلاحظ أحد — والملاحظة تأتي من عميل مُنع من
 * ميزة كان يملكها.
 */
class QueryBudgetPlanMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_assigns_the_budget_to_every_plan(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->runMigration();

        $feature = Feature::where('key', FeatureKey::QUERY_BUDGET_MONTHLY)->firstOrFail();

        foreach (['free' => 150, 'individual' => 600, 'professional' => 2000, 'team' => 6000] as $key => $expected) {
            $plan = Plan::where('key', $key)->firstOrFail();

            $this->assertSame($expected, (int) PlanFeature::where('plan_id', $plan->id)
                ->where('feature_id', $feature->id)->value('value'));
        }
    }

    #[Test]
    public function it_leaves_a_number_the_admin_already_set(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->runMigration();

        $feature = Feature::where('key', FeatureKey::QUERY_BUDGET_MONTHLY)->firstOrFail();
        $plan = Plan::where('key', 'team')->firstOrFail();

        // الآدمن رفعه يدويًّا بعد أول تشغيل.
        PlanFeature::where('plan_id', $plan->id)->where('feature_id', $feature->id)
            ->update(['value' => 25000]);

        $this->runMigration();

        $this->assertSame(25000, (int) PlanFeature::where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)->value('value'));
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_08_19_140000_assign_query_budget_to_plans.php'))->up();
    }
}
