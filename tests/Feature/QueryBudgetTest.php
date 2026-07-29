<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\Models\QueryBudget;
use App\Modules\Measurement\Models\QueryReservation;
use App\Modules\Measurement\QueryBudgetManager;
use App\Notifications\QueryBudgetWarningNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * سقف التكلفة: لا استعلام واحد خارج الميزانية (§٤.٤ و§٩).
 *
 * هذا الملف شرط لازم لبدء المرحلة ٣. المنصة قبله كانت تسجّل التكلفة **بعد**
 * الاستدعاء، أي تعرف كم أنفقت ولا تمنع دولارًا — وهو ما يجعل أول شهر باستطلاع
 * مفتوح فاتورةً غير محدودة.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private QueryBudgetManager $budgets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->budgets = app(QueryBudgetManager::class);
    }

    #[Test]
    public function a_reservation_commits_the_places_before_anything_runs(): void
    {
        $workspace = $this->workspace(limit: 10);

        $reservation = $this->budgets->reserve($workspace, 3, 'answer_presence');
        $budget = $this->budgets->budgetFor($workspace);

        // المحجوز التزامٌ فعليّ: يُخصم من المتاح قبل أن يُستدعى شيء.
        $this->assertSame(3, $budget->reserved);
        $this->assertSame(0, $budget->consumed);
        $this->assertSame(7, $budget->remaining());
        $this->assertTrue($reservation->isOpen());
    }

    #[Test]
    public function the_limit_holds_under_concurrent_reservations(): void
    {
        $workspace = $this->workspace(limit: 10);
        $granted = 0;
        $refused = 0;

        // اثنتا عشرة مهمة تطلب موضعين على سقف عشرة. الصحيح: خمس تمرّ وسبع تُرفض.
        foreach (range(1, 12) as $attempt) {
            try {
                $this->budgets->reserve($workspace, 2, "probe-{$attempt}");
                $granted++;
            } catch (BudgetExhausted) {
                $refused++;
            }
        }

        $this->assertSame(5, $granted);
        $this->assertSame(7, $refused);

        $budget = $this->budgets->budgetFor($workspace);
        $this->assertSame(10, $budget->committed());
        $this->assertLessThanOrEqual($budget->monthly_limit, $budget->committed());
    }

    #[Test]
    public function a_partial_request_is_refused_whole_not_trimmed(): void
    {
        $workspace = $this->workspace(limit: 5);
        $this->budgets->reserve($workspace, 3, 'first');

        // متبقٍّ اثنان وطُلبت خمسة. تنفيذها باثنين يُنتج رقمًا بمقام غير المعلن.
        $this->expectException(BudgetExhausted::class);
        $this->budgets->reserve($workspace, 5, 'second');
    }

    #[Test]
    public function settling_moves_the_places_from_held_to_consumed(): void
    {
        $workspace = $this->workspace(limit: 10);
        $reservation = $this->budgets->reserve($workspace, 5, 'answer_presence');

        $this->budgets->settle($reservation, costUsd: 0.042);
        $budget = $this->budgets->budgetFor($workspace)->fresh();

        $this->assertSame(0, $budget->reserved);
        $this->assertSame(5, $budget->consumed);
        $this->assertEqualsWithDelta(0.042, $budget->cost_usd, 0.000001);
        $this->assertSame(5, $budget->remaining());
    }

    #[Test]
    public function unused_places_return_to_the_budget(): void
    {
        $workspace = $this->workspace(limit: 10);
        $reservation = $this->budgets->reserve($workspace, 5, 'answer_presence');

        // توقف الاستطلاع عند ثلاث محاولات. السقف حماية من الإنفاق لا حصة تُحرق.
        $this->budgets->settle($reservation, costUsd: 0.02, actualQueries: 3);
        $budget = $this->budgets->budgetFor($workspace)->fresh();

        $this->assertSame(3, $budget->consumed);
        $this->assertSame(7, $budget->remaining());
    }

    #[Test]
    public function a_provider_failure_does_not_burn_the_places(): void
    {
        $workspace = $this->workspace(limit: 10);
        $reservation = $this->budgets->reserve($workspace, 4, 'answer_presence');

        $this->budgets->release($reservation, costUsd: 0.0);
        $budget = $this->budgets->budgetFor($workspace)->fresh();

        // لم يحصل على شيء، فلا يُحاسَب. والتكلفة صفر لأن المزوّد لم يسلّم.
        $this->assertSame(0, $budget->reserved);
        $this->assertSame(0, $budget->consumed);
        $this->assertSame(10, $budget->remaining());
        $this->assertSame(QueryReservation::STATUS_RELEASED, $reservation->fresh()->status);
    }

    #[Test]
    public function settling_twice_does_not_double_count(): void
    {
        $workspace = $this->workspace(limit: 10);
        $reservation = $this->budgets->reserve($workspace, 4, 'answer_presence');

        $this->budgets->settle($reservation, costUsd: 0.01);
        $this->budgets->settle($reservation->fresh(), costUsd: 0.01);

        $budget = $this->budgets->budgetFor($workspace)->fresh();

        // إعادة المحاولة بعد انقطاع شبكة لا تجعل الحجز يُحتسب مرتين.
        $this->assertSame(4, $budget->consumed);
        $this->assertEqualsWithDelta(0.01, $budget->cost_usd, 0.000001);
    }

    #[Test]
    public function the_warning_reaches_the_owner_once_at_eighty_percent(): void
    {
        Notification::fake();

        $workspace = $this->workspace(limit: 10);

        // تحت العتبة: لا تنبيه. §٤.٤ يحدّد ٨٠٪ لا «حين يقترب».
        $this->budgets->reserve($workspace, 7, 'first');
        Notification::assertNothingSent();

        $this->budgets->reserve($workspace, 1, 'second');

        /*
         * التوقف عند ١٠٠٪ وحده يجعل الحدّ يُكتشف بالاصطدام به وسط عمل.
         * التنبيه هو ما يمنح قرارًا قبل المنع — وكان مبنيًّا بلا مستدعٍ.
         */
        Notification::assertSentTo($workspace->owner, QueryBudgetWarningNotification::class);

        // حجز ثالث لا يعيد التنبيه: المتكرر يُقرأ ضجيجًا ويُتجاهَل معه التوقف.
        $this->budgets->reserve($workspace, 1, 'third');

        Notification::assertSentToTimes(
            $workspace->owner,
            QueryBudgetWarningNotification::class,
            1,
        );
    }

    #[Test]
    public function the_reservation_survives_crossing_the_threshold(): void
    {
        Notification::fake();

        $workspace = $this->workspace(limit: 10);

        /*
         * التنبيه أثرٌ جانبي يقع **بعد** إغلاق المعاملة: تجاوز العتبة لا يمسّ
         * الحجز نفسه، ومن بلغ ٨٠٪ يستمر عمله حتى السقف (§٤.٤ يوقف عند ١٠٠٪
         * لا عند ٨٠٪).
         */
        $this->budgets->reserve($workspace, 9, 'first');

        $budget = $this->budgets->budgetFor($workspace)->fresh();

        $this->assertSame(9, $budget->committed());
        $this->assertSame(1, $budget->remaining());
    }

    #[Test]
    public function each_month_starts_with_its_own_budget(): void
    {
        $workspace = $this->workspace(limit: 10);
        $this->budgets->reserve($workspace, 10, 'exhausted');

        $next = $this->budgets->budgetFor($workspace, period: now()->addMonth()->format('Y-m'));

        // السقف شهري: التاريخ يبقى محفوظًا والشهر الجديد يبدأ كاملًا.
        $this->assertSame(10, $next->remaining());
        $this->assertSame(2, QueryBudget::count());
    }

    #[Test]
    public function the_commercial_wallet_is_untouched_by_the_operational_cap(): void
    {
        $workspace = $this->workspace(limit: 1);
        $wallet = $workspace->wallet()->firstOrCreate([], ['balance' => 500]);
        $before = (int) $wallet->balance;

        $reservation = $this->budgets->reserve($workspace, 1, 'first');
        $this->budgets->settle($reservation, costUsd: 0.5);

        /*
         * الرصيد التجاري ما اشتراه العميل، والسقف حمايتنا من فاتورة مزوّد.
         * خلطهما يجعل عميلًا دافعًا يُمنع لأننا اقتربنا من حدّنا نحن.
         */
        $this->assertSame($before, (int) $wallet->fresh()->balance);

        $this->expectException(BudgetExhausted::class);
        $this->budgets->reserve($workspace, 1, 'second');
    }

    private function workspace(int $limit): Workspace
    {
        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();
        $workspace->forceFill(['monthly_query_limit' => $limit])->save();

        return $workspace->fresh();
    }
}
