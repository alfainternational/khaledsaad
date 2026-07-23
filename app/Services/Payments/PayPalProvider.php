<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Contracts\Payments\PaymentProvider;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayPal عبر Orders API v2.
 *
 * المفاتيح (client_id / secret) تُقرأ من جدول payment_gateways المشفّر،
 * لا من .env — فيضيفها الآدمن من اللوحة ويبدّل بين test/live بضغطة.
 */
class PayPalProvider implements PaymentProvider
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function key(): string
    {
        return 'paypal';
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $payment->id,
                    'amount' => [
                        'currency_code' => $payment->currency,
                        'value' => number_format($payment->amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                ],
            ])
            ->throw()
            ->json();

        $approveUrl = collect($response['links'] ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if ($approveUrl === null) {
            throw new RuntimeException('تعذّر إنشاء عملية الدفع لدى PayPal.');
        }

        return new CheckoutSession(
            redirectUrl: $approveUrl,
            externalId: $response['id'] ?? null,
        );
    }

    public function verify(Payment $payment, array $callbackData): bool
    {
        $orderId = $payment->external_id ?? ($callbackData['token'] ?? null);

        if ($orderId === null) {
            return false;
        }

        $token = $this->accessToken();

        // الالتقاط (capture) هو ما يحوّل الطلب المعتمد إلى دفعة فعلية.
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl()."/v2/checkout/orders/{$orderId}/capture")
            ->json();

        return ($response['status'] ?? null) === 'COMPLETED';
    }

    private function accessToken(): string
    {
        $clientId = $this->gateway->credential('client_id');
        $secret = $this->gateway->credential('secret');

        if (blank($clientId) || blank($secret)) {
            throw new RuntimeException('مفاتيح PayPal غير مكتملة في لوحة الآدمن.');
        }

        return Http::withBasicAuth($clientId, $secret)
            ->asForm()
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()
            ->json()['access_token'];
    }

    private function baseUrl(): string
    {
        return $this->gateway->isLive()
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
