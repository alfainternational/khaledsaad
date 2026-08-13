<?php

namespace Tests\Feature;

use App\Models\MarketingLearningRun;
use App\Models\CreditTransaction;
use App\Models\Tool;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_dashboard_does_not_create_a_learning_run(): void
    {
        $user = User::factory()->withoutExperience()->create();
        $user = app(ExperienceService::class)->selectInitial($user, Experience::BUSINESS);
        app(ProjectService::class)->create($user, ['name' => 'مشروع أعمال']);

        $this->actingAs($user)->get(route('app.dashboard'))->assertOk();

        $this->assertSame(0, MarketingLearningRun::query()->count());
    }

    public function test_learning_and_unclassified_users_enter_the_correct_start_surface(): void
    {
        $learner = User::factory()->withoutExperience()->create();
        $learner = app(ExperienceService::class)->selectInitial($learner, Experience::LEARNING);
        $unclassified = User::factory()->withoutExperience()->create();

        $this->actingAs($learner)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('app.learning.marketing.home'));

        $this->actingAs($unclassified)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('app.experience.choose'));
    }

    public function test_each_navigation_mode_exposes_only_its_primary_journey(): void
    {
        $business = app(ExperienceService::class)->selectInitial(
            User::factory()->withoutExperience()->create(),
            Experience::BUSINESS,
        );
        app(ProjectService::class)->create($business, ['name' => 'مشروع للتنقل']);

        $this->actingAs($business)->get(route('app.dashboard'))
            ->assertOk()
            ->assertSeeText('اليوم')
            ->assertSeeText('مشاريعي')
            ->assertSeeText('التشخيص')
            ->assertDontSeeText('مساري')
            ->assertDontSeeText('تطبيقاتي');

        $learner = app(ExperienceService::class)->selectInitial(
            User::factory()->withoutExperience()->create(),
            Experience::LEARNING,
        );

        $this->actingAs($learner)->get(route('app.learning.marketing.home'))
            ->assertOk()
            ->assertSeeText('مساري')
            ->assertSeeText('الدروس')
            ->assertSeeText('تطبيقاتي')
            ->assertDontSeeText('المشاريع')
            ->assertDontSeeText('التشخيصات');
    }

    public function test_each_dashboard_presents_exactly_one_primary_next_action(): void
    {
        $business = app(ExperienceService::class)->selectInitial(
            User::factory()->withoutExperience()->create(),
            Experience::BUSINESS,
        );
        app(ProjectService::class)->create($business, ['name' => 'مشروع واحد']);
        $businessResponse = $this->actingAs($business)->get(route('app.dashboard'))->assertOk();
        $this->assertSame(1, substr_count($businessResponse->getContent(), 'data-primary-action'));

        $learner = app(ExperienceService::class)->selectInitial(
            User::factory()->withoutExperience()->create(),
            Experience::LEARNING,
        );
        $learningResponse = $this->actingAs($learner)->get(route('app.learning.marketing.home'))->assertOk();
        $this->assertSame(1, substr_count($learningResponse->getContent(), 'data-primary-action'));
    }

    public function test_business_tool_discloses_the_run_cost_before_starting(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->withoutExperience()->create(),
            Experience::BUSINESS,
        );
        app(ProjectService::class)->create($user, ['name' => 'مشروع التكلفة']);
        $tool = Tool::query()->where('key', 'marketing-score')->firstOrFail();

        $this->actingAs($user)
            ->get(route('app.tools.show', $tool->key))
            ->assertOk()
            ->assertSeeText("تكلفة هذا التشغيل: {$tool->currentVersion->credit_cost} رصيد");
    }

    public function test_billing_hides_zero_value_technical_transactions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->withoutExperience()->create(),
            Experience::BUSINESS,
        );
        $wallet = $user->primaryWorkspace()->wallet;

        CreditTransaction::create([
            'credit_wallet_id' => $wallet->id,
            'type' => CreditTransaction::TYPE_CHARGE,
            'amount' => 0,
            'balance_after' => $wallet->balance,
            'reason' => 'حركة تقنية صفرية',
        ]);
        CreditTransaction::create([
            'credit_wallet_id' => $wallet->id,
            'type' => CreditTransaction::TYPE_GRANT,
            'amount' => 1,
            'balance_after' => $wallet->balance + 1,
            'reason' => 'رصيد مفهوم',
        ]);

        $this->actingAs($user)->get(route('app.billing'))
            ->assertOk()
            ->assertDontSeeText('حركة تقنية صفرية')
            ->assertSeeText('رصيد مفهوم');
    }
}
