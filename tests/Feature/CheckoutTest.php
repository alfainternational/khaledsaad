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
    public function buying_a_credit_pack_via_the_manual_gateway_waits_for_approval_then_grants(): void
    {
        // التحويل اليدوي لا يمنح بمجرد الضغط: يُسجَّل معلّقًا ثم يعتمده الآدمن.
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $before = $user->primaryWorkspace()->wallet->balance;

        $this->actingAs($user)
            ->post(route('app.checkout.pack', $pack))
            ->assertRedirect(route('app.billing'));

        $this->assertSame($before, $user->primaryWorkspace()->wallet->fresh()->balance);
        $this->assertDatabaseHas('payments', [
            'credit_pack_id' => $pack->id,
            'status' => Payment::STATUS_PENDING,
        ]);

        $payment = Payment::where('credit_pack_id', $pack->id)->latest('id')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.payments.approve', $payment))->assertRedirect();

        $this->assertSame($before + $pack->credits, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function a_paid_plan_activates_only_after_the_payment_is_confirmed(): void
    {
        $user = User::factory()->create();
        $plan = Plan::where('key', 'professional')->firstOrFail();

        $this->actingAs($user)->post(route('app.checkout.plan', $plan))->assertRedirect();

        // معلّق: الخطة لم تُفعّل بعد.
        $this->assertSame('free', $user->primaryWorkspace()->subscription->fresh()->plan->key);

        $payment = Payment::where('plan_id', $plan->id)->latest('id')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.payments.approve', $payment));

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
    public function customer_can_choose_any_active_configured_gateway_and_the_payment_keeps_that_choice(): void
    {
        $user = User::factory()->create();
        $pack = CreditPack::active()->firstOrFail();
        $manual = PaymentGateway::where('provider', 'manual')->firstOrFail();
        $paypal = PaymentGateway::where('provider', 'paypal')->firstOrFail();
        $paypal->update([
            'is_active' => true,
            'is_default' => true,
            'credentials' => ['client_id' => 'cid', 'secret' => 'secret'],
        ]);

        $this->actingAs($user)->get(route('app.billing'))
            ->assertOk()
            ->assertSee('PayPal')
            ->assertSee('تحويل بنكي')
            ->assertSee('name="gateway_id"', false);

        $this->actingAs($user)->post(route('app.checkout.pack', $pack), [
            'gateway_id' => $manual->id,
        ])->assertRedirect(route('app.billing'));

        $this->assertDatabaseHas('payments', [
            'credit_pack_id' => $pack->id,
            'payment_gateway_id' => $manual->id,
            'provider' => 'manual',
        ]);
    }

    #[Test]
    public function an_inactive_gateway_cannot_be_selected_from_web_or_api(): void
    {
        $user = User::factory()->create();
        $pack = CreditPack::active()->firstOrFail();
        $inactive = PaymentGateway::where('provider', 'paypal')->firstOrFail();

        $this->actingAs($user)->post(route('app.checkout.pack', $pack), [
            'gateway_id' => $inactive->id,
        ])->assertSessionHasErrors('gateway_id');

        Sanctum::actingAs($user);
        $this->postJson(route('api.v1.checkout.pack', $pack), [
            'gateway_id' => $inactive->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('payments', ['payment_gateway_id' => $inactive->id]);
    }

    #[Test]
    public function completing_a_payment_twice_does_not_double_grant(): void
    {
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $before = $user->primaryWorkspace()->wallet->balance;

        $this->actingAs($user)->post(route('app.checkout.pack', $pack));

        $payment = Payment::where('credit_pack_id', $pack->id)->latest('id')->firstOrFail();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.payments.approve', $payment));

        $balanceAfterFirst = $user->primaryWorkspace()->wallet->fresh()->balance;

        // إعادة استدعاء callback بعد الاعتماد لا تمنح مرة ثانية (idempotent).
        $this->actingAs($user)->get(route('app.checkout.callback', $payment));

        $this->assertSame($balanceAfterFirst, $user->primaryWorkspace()->wallet->fresh()->balance);
        $this->assertSame($before + $pack->credits, $balanceAfterFirst);
    }

    #[Test]
    public function the_app_records_a_pending_pack_purchase_via_the_manual_gateway(): void
    {
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $before = $user->primaryWorkspace()->wallet->balance;

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.checkout.pack', $pack))
            ->assertOk()
            ->assertJsonPath('data.completed', false)
            ->assertJsonPath('data.pending_approval', true);

        // نفس قاعدة الويب حرفيًا: لا رصيد قبل اعتماد التحويل.
        $this->assertSame($before, $user->primaryWorkspace()->wallet->fresh()->balance);
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
