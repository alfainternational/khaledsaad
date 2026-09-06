<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
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
 * INV-9 — كل كتابة على الرصيد قابلة للتكرار بلا أثر مضاعف.
 *
 * ما يحرسه: الخصم المزدوج الصامت. كل حركة على حدة صحيحة، والمجموع خاطئ،
 * ولا يظهر في سجلّ ولا يشتكي منه إلا مستخدم يعدّ رصيده بنفسه.
 */
class CreditIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function holding_twice_for_one_run_deducts_once(): void
    {
        [$run, $wallet] = $this->fundedRun(100);

        app(CreditManager::class)->hold($run, 5);
        app(CreditManager::class)->hold($run, 5);
        app(CreditManager::class)->hold($run, 5);

        $this->assertSame(95, $wallet->refresh()->balance, 'ثلاثة نداءات خصمت ثلاث مرات.');
        $this->assertSame(1, CreditTransaction::where('tool_run_id', $run->id)
            ->where('type', CreditTransaction::TYPE_HOLD)->count());
    }

    #[Test]
    public function refunding_twice_returns_the_hold_once(): void
    {
        [$run, $wallet] = $this->fundedRun(100);

        app(CreditManager::class)->hold($run, 5);
        app(CreditManager::class)->refund($run);
        app(CreditManager::class)->refund($run);

        $this->assertSame(100, $wallet->refresh()->balance, 'الاسترداد المكرر منح رصيدًا مجانيًّا.');
    }

    #[Test]
    public function charging_twice_never_deducts_again(): void
    {
        [$run, $wallet] = $this->fundedRun(100);

        app(CreditManager::class)->hold($run, 5);
        app(CreditManager::class)->charge($run);
        app(CreditManager::class)->charge($run);

        $this->assertSame(95, $wallet->refresh()->balance);
    }

    /**
     * إشعار بوابة الدفع يصل مرتين بحكم تصميمه.
     */
    #[Test]
    public function a_grant_with_the_same_key_credits_once(): void
    {
        [$run, $wallet] = $this->fundedRun(0);
        $workspace = $run->project->workspace;

        app(CreditManager::class)->grant($workspace, 50, 'شراء حزمة', 'grant:payment:99');
        app(CreditManager::class)->grant($workspace, 50, 'شراء حزمة', 'grant:payment:99');

        $this->assertSame(50, $wallet->refresh()->balance, 'إشعار مكرر منح الرصيد مرتين.');
    }

    /**
     * ومنحتان مختلفتان حقًّا تمرّان معًا — الحماية ليست منعًا للمنح.
     */
    #[Test]
    public function two_genuinely_different_grants_both_apply(): void
    {
        [$run, $wallet] = $this->fundedRun(0);
        $workspace = $run->project->workspace;

        app(CreditManager::class)->grant($workspace, 50, 'شراء', 'grant:payment:1');
        app(CreditManager::class)->grant($workspace, 50, 'شراء', 'grant:payment:2');

        $this->assertSame(100, $wallet->refresh()->balance);
    }

    /**
     * @return array{0: ToolRun, 1: CreditWallet}
     */
    private function fundedRun(int $balance): array
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع التكرار']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $wallet = app(CreditManager::class)->walletFor($project->workspace);
        $wallet->forceFill(['balance' => $balance])->save();

        return [$run, $wallet];
    }
}
