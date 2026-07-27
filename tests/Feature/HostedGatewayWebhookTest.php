<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HostedGatewayWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function moyasar_webhook_is_server_verified_and_replay_safe(): void
    {
        $user = User::factory()->create();
        $gateway = PaymentGateway::create([
            'provider' => 'moyasar', 'label' => 'ميسّر', 'mode' => 'test',
            'is_active' => true, 'credentials' => ['secret_key' => 'sk', 'webhook_secret' => 'hook'],
        ]);
        $payment = Payment::create([
            'workspace_id' => $user->primaryWorkspace()->id, 'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id, 'provider' => 'moyasar',
            'purpose' => 'credits', 'amount' => 49, 'currency' => 'SAR',
            'charged_amount' => 49, 'charged_currency' => 'SAR',
            'external_id' => 'inv-1', 'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake(['https://api.moyasar.com/v1/invoices/inv-1' => Http::response([
            'id' => 'inv-1', 'status' => 'paid', 'amount' => 4900, 'currency' => 'SAR',
            'payments' => [['id' => 'pay-1', 'status' => 'paid', 'amount' => 4900, 'currency' => 'SAR']],
        ])]);

        $payload = ['id' => 'inv-1', 'status' => 'paid', 'metadata' => ['payment_id' => (string) $payment->id]];
        $this->postJson(route('webhooks.moyasar'), $payload)->assertOk();
        $this->postJson(route('webhooks.moyasar'), $payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }
}
