<?php

namespace Tests\Feature;

use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تدفّق الشراء: من اختيار الحزمة إلى منح الرصيد، عبر البوابة المفعّلة.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function buying_a_credit_pack_via_the_manual_gateway_grants_credits(): void
    {
        // البوابة اليدوية مفعّلة من البذر، فالشراء يكتمل مباشرة.
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $before = $user->primaryWorkspace()->wallet->balance;

        $this->actingAs($user)
            ->post(route('app.checkout.pack', $pack))
            ->assertRedirect(route('app.billing'));

        $wallet = $user->primaryWorkspace()->wallet->fresh();
        $this->assertSame($before + $pack->credits, $wallet->balance);
        $this->assertDatabaseHas('payments', [
            'credit_pack_id' => $pack->id,
            'status' => Payment::STATUS_PAID,
        ]);
    }

    #[Test]
    public function buying_a_paid_plan_activates_it_after_payment(): void
    {
        $user = User::factory()->create();
        $plan = Plan::where('key', 'professional')->firstOrFail();

        $this->actingAs($user)->post(route('app.checkout.plan', $plan))->assertRedirect();

        $this->assertSame('professional', $user->primaryWorkspace()->subscription->fresh()->plan->key);
    }

    #[Test]
    public function a_free_plan_activates_without_going_through_payment(): void
    {
        $user = User::factory()->create();
        $free = Plan::where('key', 'free')->firstOrFail();

        $this->actingAs($user)
            ->post(route('app.checkout.plan', $free))
            ->assertRedirect(route('app.billing'));

        $this->assertSame('free', $user->primaryWorkspace()->subscription->plan->key);
        $this->assertDatabaseMissing('payments', ['plan_id' => $free->id]);
    }

    #[Test]
    public function purchase_is_blocked_when_no_gateway_is_active(): void
    {
        PaymentGateway::query()->update(['is_active' => false]);

        $user = User::factory()->create();
        $pack = CreditPack::active()->first();

        $this->actingAs($user)
            ->post(route('app.checkout.pack', $pack))
            ->assertRedirect()
            ->assertSessionHasErrors('gateway');

        $this->assertDatabaseMissing('payments', ['credit_pack_id' => $pack->id, 'status' => 'paid']);
    }

    #[Test]
    public function completing_a_payment_twice_does_not_double_grant(): void
    {
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $before = $user->primaryWorkspace()->wallet->balance;

        // شراء أول يكتمل ويمنح.
        $this->actingAs($user)->post(route('app.checkout.pack', $pack));

        $payment = Payment::where('credit_pack_id', $pack->id)->latest('id')->firstOrFail();
        $balanceAfterFirst = $user->primaryWorkspace()->wallet->fresh()->balance;

        // إعادة استدعاء callback لا تمنح مرة ثانية (idempotent).
        $this->actingAs($user)->get(route('app.checkout.callback', $payment));

        $this->assertSame($balanceAfterFirst, $user->primaryWorkspace()->wallet->fresh()->balance);
        $this->assertSame($before + $pack->credits, $balanceAfterFirst);
    }

    #[Test]
    public function the_app_completes_a_pack_purchase_via_the_manual_gateway(): void
    {
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $before = $user->primaryWorkspace()->wallet->balance;

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.checkout.pack', $pack))
            ->assertOk()
            ->assertJsonPath('data.completed', true);

        $this->assertSame($before + $pack->credits, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function the_app_refuses_to_grant_a_paid_plan_without_payment(): void
    {
        // ثغرة كانت تسمح بتفعيل خطة مدفوعة عبر التطبيق دون دفع؛ الآن مرفوضة.
        $user = User::factory()->create();
        $paid = Plan::where('key', 'professional')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.billing.subscribe', $paid))->assertStatus(422);
        $this->assertSame('free', $user->primaryWorkspace()->subscription->plan->key);
    }
}
