<?php

namespace Tests\Feature;

use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الدفع الفعلي: PayPal بوابة عاملة لا وعدًا، والتحويل اليدوي لا يمنح رصيدًا
 * إلا باعتماد آدمن. كل اختبار هنا يمثّل حالة كانت تُفقد مالًا أو تمنحه بلا وجه.
 */
class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function a_gateway_cannot_be_activated_before_its_required_keys_are_set(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $paypal = PaymentGateway::where('provider', 'paypal')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.gateways.toggle', $paypal))
            ->assertSessionHasErrors('gateway');

        $this->assertFalse($paypal->fresh()->is_active);
    }

    #[Test]
    public function a_paypal_purchase_converts_the_price_and_redirects_to_the_approval_url(): void
    {
        $this->activatePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-1',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve/ORDER-1']],
            ]),
        ]);

        $user = User::factory()->create();
        $pack = CreditPack::active()->first();

        $this->actingAs($user)
            ->post(route('app.checkout.pack', $pack))
            ->assertRedirect('https://paypal.test/approve/ORDER-1');

        $payment = Payment::latest('id')->firstOrFail();

        $this->assertSame('ORDER-1', $payment->external_id);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        // السعر بالريال، والتحصيل بعملة البوابة بمعامل الآدمن.
        $this->assertSame('USD', $payment->charged_currency);
        $this->assertEqualsWithDelta(round($pack->price * 0.25, 2), $payment->charged_amount, 0.001);

        // لا رصيد قبل التقاط مؤكَّد.
        $this->assertSame(5, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function returning_from_paypal_captures_the_order_and_grants_the_credits(): void
    {
        $this->activatePayPal();
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $payment = $this->pendingPayPalPayment($user, $pack);

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER-9/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-9',
                        'amount' => ['value' => number_format($payment->charged_amount, 2, '.', ''), 'currency_code' => 'USD'],
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($user)->get(route('app.checkout.callback', $payment))->assertRedirect();

        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertSame('CAPTURE-9', $payment->external_capture_id);
        $this->assertSame(5 + $pack->credits, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function a_capture_with_a_different_amount_grants_nothing(): void
    {
        $this->activatePayPal();
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $payment = $this->pendingPayPalPayment($user, $pack);

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER-9/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-X',
                        // مبلغ لا يطابق ما بدأنا به: لا نمنح مهما قال الرد.
                        'amount' => ['value' => '0.01', 'currency_code' => 'USD'],
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($user)->get(route('app.checkout.callback', $payment));

        $this->assertFalse($payment->fresh()->isPaid());
        $this->assertSame('amount_mismatch', $payment->fresh()->failure_reason);
        $this->assertSame(5, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function the_webhook_grants_credits_when_paypal_signs_the_event(): void
    {
        $this->activatePayPal();
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();
        $payment = $this->pendingPayPalPayment($user, $pack);

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/ORDER-9/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-W',
                        'amount' => ['value' => number_format($payment->charged_amount, 2, '.', ''), 'currency_code' => 'USD'],
                    ]]],
                ]],
            ]),
        ]);

        // العميل أغلق المتصفح بعد الدفع: الإشعار وحده يكفي لوصول الرصيد.
        $this->postJson(route('webhooks.paypal'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['custom_id' => (string) $payment->id],
        ])->assertOk();

        $this->assertTrue($payment->fresh()->isPaid());
        $this->assertSame(5 + $pack->credits, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function an_unsigned_webhook_is_rejected(): void
    {
        $this->activatePayPal();
        $user = User::factory()->create();
        $payment = $this->pendingPayPalPayment($user, CreditPack::active()->first());

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);

        $this->postJson(route('webhooks.paypal'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['custom_id' => (string) $payment->id],
        ])->assertStatus(401);

        $this->assertFalse($payment->fresh()->isPaid());
        $this->assertSame(5, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function a_manual_transfer_waits_for_admin_approval_before_any_credit(): void
    {
        $user = User::factory()->create();
        $pack = CreditPack::active()->first();

        $this->actingAs($user)->post(route('app.checkout.pack', $pack))->assertRedirect(route('app.billing'));

        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(5, $user->primaryWorkspace()->wallet->fresh()->balance);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.payments.approve', $payment))->assertRedirect();

        $this->assertTrue($payment->fresh()->isPaid());
        $this->assertSame($admin->id, $payment->fresh()->approved_by);
        $this->assertSame(5 + $pack->credits, $user->primaryWorkspace()->wallet->fresh()->balance);

        // اعتماد ثانٍ لا يمنح مرتين.
        $this->actingAs($admin)->post(route('admin.payments.approve', $payment));
        $this->assertSame(5 + $pack->credits, $user->primaryWorkspace()->wallet->fresh()->balance);
    }

    #[Test]
    public function a_paid_plan_is_not_granted_from_the_billing_page_without_payment(): void
    {
        $user = User::factory()->create();
        $paid = Plan::where('key', 'professional')->firstOrFail();

        $this->actingAs($user)
            ->post(route('app.billing.subscribe', $paid))
            ->assertSessionHasErrors('plan');

        $this->assertSame('free', $user->primaryWorkspace()->subscription->fresh()->plan->key);
    }

    private function activatePayPal(): void
    {
        PaymentGateway::query()->update(['is_active' => false]);

        PaymentGateway::where('provider', 'paypal')->firstOrFail()->update([
            'is_active' => true,
            'currency' => 'USD',
            'fx_rate' => 0.25,
            'credentials' => ['client_id' => 'id', 'secret' => 'secret', 'webhook_id' => 'WH-1'],
        ]);
    }

    private function pendingPayPalPayment(User $user, CreditPack $pack): Payment
    {
        return Payment::create([
            'workspace_id' => $user->primaryWorkspace()->id,
            'user_id' => $user->id,
            'provider' => 'paypal',
            'purpose' => 'credit_pack',
            'credit_pack_id' => $pack->id,
            'amount' => $pack->price,
            'currency' => $pack->currency,
            'charged_amount' => round($pack->price * 0.25, 2),
            'charged_currency' => 'USD',
            'credits_granted' => $pack->credits,
            'status' => Payment::STATUS_PENDING,
            'external_id' => 'ORDER-9',
        ]);
    }
}
