<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Billing\CreditManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Failures\FailureKind;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * INV-5 — لا حالة نهائية اسمها «ضاع مجهودك».
 *
 * ما تحرسه: أن عطلًا لدينا يُنتج انتظارًا يُعاد تلقائيًّا، لا فشلًا
 * ينتظر أن يتذكّر المستخدم العودة والضغط على زر.
 */
class DeferredRunRecoveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_run_awaiting_capacity_is_not_terminal_and_not_stale(): void
    {
        $run = $this->newRun();
        $run->forceFill(['status' => ToolRun::STATUS_AWAITING_CAPACITY])->save();

        $this->assertFalse($run->isTerminal(), 'الانتظار عُومل كحالة نهائية.');
        $this->assertFalse($run->isStale(), 'المنتظِر عُدَّ عالقًا وهو في طابورنا.');
        $this->assertTrue($run->isAwaitingCapacity());
    }

    /**
     * الشاشة تقول «ننتظر» لا «تعذّر»، وبلا زر يطلب منه ما نفعله نحن.
     */
    #[Test]
    public function the_waiting_screen_promises_a_retry_instead_of_demanding_one(): void
    {
        $run = $this->newRun();
        $run->forceFill([
            'status' => ToolRun::STATUS_AWAITING_CAPACITY,
            'failure_kind' => FailureKind::Ours->value,
            'failure_code' => 'provider_unavailable',
            'failure_reason' => 'إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء.',
        ])->save();

        $response = $this->actingAs($run->project->workspace->owner)
            ->get(route('app.runs.status', $run->uuid))
            ->assertOk();

        $response->assertSeeText('في الانتظار');
        $response->assertSeeText('لم يُخصم من رصيدك شيء');
        $response->assertDontSeeText('أعد المحاولة الآن');
    }

    /**
     * الأمر يرفض الإعادة ما دامت القدرة لم تعد — وإلا حرق محاولةً بلا طائل.
     */
    #[Test]
    public function deferred_runs_are_requeued_only_when_capacity_returns(): void
    {
        Queue::fake();

        $run = $this->newRun();
        $run->forceFill([
            'status' => ToolRun::STATUS_AWAITING_CAPACITY,
            'retry_after' => now()->subMinute(),
        ])->save();

        $this->artisan('runs:resume')->assertExitCode(0);

        $this->assertSame(
            ToolRun::STATUS_QUEUED,
            $run->refresh()->status,
            'التشغيل المؤجَّل لم يُعد إلى الطابور رغم توفّر القدرة.',
        );
    }

    /**
     * الإنقاذ يرحّل عطلَنا، ولا يمسّ حدًّا يخصّ المستخدم.
     */
    #[Test]
    public function the_rescue_command_moves_our_failures_only(): void
    {
        $ours = $this->newRun();
        $ours->forceFill(['status' => ToolRun::STATUS_FAILED, 'failure_kind' => FailureKind::Ours->value])->save();

        $theirs = $this->newRun();
        $theirs->forceFill(['status' => ToolRun::STATUS_FAILED, 'failure_kind' => FailureKind::Theirs->value])->save();

        $this->artisan('runs:rescue')->assertExitCode(0);

        $this->assertSame(ToolRun::STATUS_AWAITING_CAPACITY, $ours->refresh()->status);
        $this->assertSame(
            ToolRun::STATUS_FAILED,
            $theirs->refresh()->status,
            'حدُّ المستخدم رُحِّل، فستُحرق محاولة وتُنتج الفشل نفسه.',
        );
    }

    /**
     * والتشغيل القديم بلا تصنيف يُفترض عطلَنا — براءةُ المستخدم أرخص.
     */
    #[Test]
    public function an_unclassified_old_failure_is_treated_as_ours(): void
    {
        $legacy = $this->newRun();
        $legacy->forceFill(['status' => ToolRun::STATUS_FAILED, 'failure_kind' => null])->save();

        $this->artisan('runs:rescue')->assertExitCode(0);

        $this->assertSame(ToolRun::STATUS_AWAITING_CAPACITY, $legacy->refresh()->status);
    }

    #[Test]
    public function the_dry_run_changes_nothing(): void
    {
        $run = $this->newRun();
        $run->forceFill(['status' => ToolRun::STATUS_FAILED, 'failure_kind' => FailureKind::Ours->value])->save();

        $this->artisan('runs:rescue --dry')->assertExitCode(0);

        $this->assertSame(ToolRun::STATUS_FAILED, $run->refresh()->status);
    }

    private function newRun(): ToolRun
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع '.uniqid()]);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        app(CreditManager::class)->walletFor($project->workspace)
            ->forceFill(['balance' => 500])->save();

        return app(ToolRunService::class)->start($project, $tool, $user);
    }
}
