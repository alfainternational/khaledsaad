<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_refund_a_paid_payment_and_cannot_exceed_the_remaining_amount(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $gateway = PaymentGateway::create([
            'provider' => 'manual', 'label' => 'تحويل', 'mode' => 'live',
            'is_active' => true, 'credentials' => [],
        ]);
        $payment = Payment::create([
            'workspace_id' => $customer->primaryWorkspace()->id,
            'user_id' => $customer->id,
            'payment_gateway_id' => $gateway->id,
            'provider' => 'manual', 'purpose' => 'credits',
            'amount' => 100, 'currency' => 'SAR',
            'charged_amount' => 100, 'charged_currency' => 'SAR',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), [
            'amount' => 40,
            'reason' => 'requested_by_customer',
        ])->assertRedirect();

        $this->assertDatabaseHas('payment_refunds', [
            'payment_id' => $payment->id, 'amount' => 40, 'status' => 'completed',
        ]);
        $this->assertSame(40.0, $payment->fresh()->refunded_amount);

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), [
            'amount' => 70,
            'reason' => 'requested_by_customer',
        ])->assertSessionHasErrors('refund');

        $this->assertSame(40.0, $payment->fresh()->refunded_amount);
    }
}
