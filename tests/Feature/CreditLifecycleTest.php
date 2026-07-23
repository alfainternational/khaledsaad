<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Billing\CreditManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * دورة الأرصدة: حجز عند البدء، خصم عند النجاح، استرداد عند الفشل (BR-004، BR-011).
 */
class CreditLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function a_new_workspace_gets_a_wallet_and_free_plan(): void
    {
        $workspace = User::factory()->create()->primaryWorkspace();

        $this->assertNotNull($workspace->wallet);
        $this->assertSame(5, $workspace->wallet->balance);
        $this->assertSame('free', $workspace->subscription->plan->key);
    }

    #[Test]
    public function queueing_a_run_holds_credits_and_a_failure_refunds_them(): void
    {
        Queue::fake();

        $run = $this->draftRun();
        $wallet = $run->project->workspace->wallet;
        $cost = $run->toolVersion->credit_cost;

        $this->assertGreaterThan(0, $cost);
        $startingBalance = $wallet->balance;

        app(ToolRunService::class)->queue($run);

        // BR-004: الرصيد محجوز بعد الطلب.
        $this->assertSame($startingBalance - $cost, $wallet->refresh()->balance);

        // BR-011: الفشل التقني يسترد الحجز كاملًا.
        app(CreditManager::class)->refund($run);
        $this->assertSame($startingBalance, $wallet->refresh()->balance);
        $this->assertTrue(
            CreditTransaction::where('tool_run_id', $run->id)
                ->where('type', CreditTransaction::TYPE_REFUND)->exists(),
        );
    }

    #[Test]
    public function a_successful_run_charges_the_hold_without_double_deduction(): void
    {
        Queue::fake();

        $run = $this->draftRun();
        $wallet = $run->project->workspace->wallet;
        $cost = $run->toolVersion->credit_cost;
        $startingBalance = $wallet->balance;

        app(ToolRunService::class)->queue($run);
        app(CreditManager::class)->charge($run);

        // الخصم النهائي لا يخصم مرة ثانية: الرصيد نقص بمقدار التكلفة فقط.
        $this->assertSame($startingBalance - $cost, $wallet->refresh()->balance);
    }

    #[Test]
    public function an_insufficient_balance_blocks_the_run_before_any_ai_call(): void
    {
        Queue::fake();

        $run = $this->draftRun();
        $run->project->workspace->wallet->forceFill(['balance' => 0])->save();

        $this->expectExceptionMessage('رصيدك غير كافٍ');
        app(ToolRunService::class)->queue($run);

        Queue::assertNothingPushed();
    }

    private function draftRun(): ToolRun
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الرصيد']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $steps = [
            1 => ['business_model' => 'services', 'description' => str_repeat('وصف واضح ', 4), 'geography' => 'الرياض', 'monthly_budget' => 3000],
            2 => ['primary_goal' => 'leads', 'value_proposition' => 'نسلّم خلال 48 ساعة أو نعيد المبلغ كاملًا بلا أسئلة', 'audience_clarity' => 'documented'],
            3 => ['active_channels' => ['seo'], 'tracking_maturity' => 'full', 'content_cadence' => 'weekly'],
            4 => ['landing_experience' => 'optimized', 'retention_motion' => 'systematic', 'known_cac' => 90],
        ];

        foreach ($steps as $step => $input) {
            app(ToolRunService::class)->saveStep($run, $step, $input);
        }

        return $run->refresh();
    }
}
