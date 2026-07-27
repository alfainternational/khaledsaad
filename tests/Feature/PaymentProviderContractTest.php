<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Payments\MoyasarProvider;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\TapProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentProviderContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_catalogue_contains_four_real_provider_types_and_multiple_can_be_active(): void
    {
        $this->assertSame(['manual', 'moyasar', 'paypal', 'tap'], array_keys(collect(PaymentGatewayManager::catalogue())->sortKeys()->all()));

        $manual = PaymentGateway::create(['provider' => 'manual', 'label' => 'تحويل', 'mode' => 'live', 'is_active' => true]);
        $moyasar = PaymentGateway::create([
            'provider' => 'moyasar', 'label' => 'ميسّر', 'mode' => 'test', 'is_active' => true,
            'is_default' => true, 'credentials' => ['secret_key' => 'sk_test_1', 'webhook_secret' => 'hook'],
        ]);

        $manager = app(PaymentGatewayManager::class);
        $this->assertSame([$moyasar->id, $manual->id], $manager->activeGateways()->pluck('id')->all());
        $this->assertSame($moyasar->id, $manager->defaultGateway()?->id);
    }

    #[Test]
    public function moyasar_creates_a_hosted_invoice_and_verifies_it_from_the_server(): void
    {
        $gateway = PaymentGateway::create([
            'provider' => 'moyasar', 'label' => 'ميسّر', 'mode' => 'test', 'is_active' => true,
            'currency' => 'SAR', 'credentials' => ['secret_key' => 'sk_test_1', 'webhook_secret' => 'hook'],
        ]);
        $payment = $this->payment('moyasar', $gateway);

        Http::fake([
            'https://api.moyasar.com/v1/invoices' => Http::response([
                'id' => 'inv-1', 'status' => 'initiated', 'url' => 'https://checkout.moyasar.com/invoices/inv-1',
            ], 201),
            'https://api.moyasar.com/v1/invoices/inv-1' => Http::response([
                'id' => 'inv-1', 'status' => 'paid', 'amount' => 4900, 'currency' => 'SAR',
                'payments' => [['id' => 'pay-1', 'status' => 'paid', 'amount' => 4900, 'currency' => 'SAR']],
            ]),
        ]);

        $provider = new MoyasarProvider($gateway);
        $session = $provider->createCheckout($payment, 'https://example.test/return', 'https://example.test/cancel');

        $this->assertSame('inv-1', $session->externalId);
        $this->assertSame('https://checkout.moyasar.com/invoices/inv-1', $session->redirectUrl);
        $payment->forceFill(['external_id' => $session->externalId])->save();
        $this->assertTrue($provider->verify($payment, []));
        $this->assertSame('pay-1', $payment->fresh()->external_capture_id);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.moyasar.com/v1/invoices'
            && $request['amount'] === 4900
            && $request['metadata']['payment_id'] === (string) $payment->id);
    }

    #[Test]
    public function tap_creates_a_redirect_charge_and_verifies_the_stored_charge(): void
    {
        $gateway = PaymentGateway::create([
            'provider' => 'tap', 'label' => 'Tap', 'mode' => 'test', 'is_active' => true,
            'currency' => 'SAR', 'credentials' => ['secret_key' => 'sk_test_tap', 'merchant_id' => 'merchant-1'],
        ]);
        $payment = $this->payment('tap', $gateway);

        Http::fake([
            'https://api.tap.company/v2/charges/' => Http::response([
                'id' => 'chg-1', 'status' => 'INITIATED', 'transaction' => ['url' => 'https://tap.test/pay/chg-1'],
            ]),
            'https://api.tap.company/v2/charges/chg-1' => Http::response([
                'id' => 'chg-1', 'status' => 'CAPTURED', 'amount' => 49.0, 'currency' => 'SAR',
                'reference' => ['order' => (string) $payment->id],
            ]),
        ]);

        $provider = new TapProvider($gateway);
        $session = $provider->createCheckout($payment, 'https://example.test/return', 'https://example.test/cancel');

        $this->assertSame('chg-1', $session->externalId);
        $this->assertSame('https://tap.test/pay/chg-1', $session->redirectUrl);
        $payment->forceFill(['external_id' => $session->externalId])->save();
        $this->assertTrue($provider->verify($payment, []));
        $this->assertSame('chg-1', $payment->fresh()->external_capture_id);
    }

    private function payment(string $provider, PaymentGateway $gateway): Payment
    {
        $user = User::factory()->create();

        return Payment::create([
            'workspace_id' => $user->primaryWorkspace()->id,
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'provider' => $provider,
            'purpose' => 'plan',
            'amount' => 49,
            'currency' => 'SAR',
            'credits_granted' => 40,
            'status' => Payment::STATUS_PENDING,
        ]);
    }
}
