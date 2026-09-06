<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Billing\CreditManager;
use App\Services\Projects\ProjectService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * البوابة على الاستشارة الشاملة — موقع العطل A1 الأصلي.
 *
 * ما تحرسه: ألّا يبدأ أحد ستين سؤالًا وهو لا يعرف أن الحزمة تكلّف أكثر
 * مما يملك. الجدار كان قائمًا قبل الزر، ولم يكن مكتوبًا عليه شيء.
 */
class ConsultationPreflightTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_bundle_cost_is_disclosed_before_the_first_question(): void
    {
        $user = $this->userWithBalance(1000);

        $this->actingAs($user)
            ->get(route('app.consultations.index'))
            ->assertOk()
            ->assertSeeText('الاستشارة الشاملة')
            ->assertSeeText('تكلّف')
            ->assertSeeText('رصيدك');
    }

    /**
     * رصيد لا يكفي الحزمة كاملة ليس منعًا: يبدأ بما يكفيه ويعرف الباقي.
     */
    #[Test]
    public function a_partial_budget_offers_the_affordable_subset_not_a_closed_door(): void
    {
        $user = $this->userWithBalance(5);

        $response = $this->actingAs($user)
            ->get(route('app.consultations.index'))
            ->assertOk();

        $response->assertSeeText('رصيدك يكفي');
    }

    /**
     * رصيد صفر: الحدّ معلن قبل البدء ومعه إجراؤه، لا بعد آخر سؤال.
     */
    #[Test]
    public function a_zero_balance_is_told_before_starting_and_the_button_is_disabled(): void
    {
        $user = $this->userWithBalance(0);

        $response = $this->actingAs($user)
            ->get(route('app.consultations.index'))
            ->assertOk();

        $response->assertSeeText('ينقصك');
        $response->assertSee('disabled', false);
    }

    /**
     * ولا تُطبع القيمة الخام لحالة الجلسة على الشاشة (INV-3).
     */
    #[Test]
    public function the_session_status_is_never_printed_raw(): void
    {
        $user = $this->userWithBalance(1000);

        $this->actingAs($user)
            ->get(route('app.consultations.index'))
            ->assertOk()
            ->assertDontSee('analysis_queued')
            ->assertDontSee('الحالة: active');
    }

    private function userWithBalance(int $balance): User
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الحزمة']);

        app(CreditManager::class)->walletFor($project->workspace)
            ->forceFill(['balance' => $balance])->save();

        return $user;
    }
}
