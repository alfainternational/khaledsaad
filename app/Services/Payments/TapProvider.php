<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Contracts\Payments\GatewayHealth;
use App\Contracts\Payments\PaymentProvider;
use App\Contracts\Payments\RefundResult;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TapProvider implements PaymentProvider
{
    private const BASE_URL = 'https://api.tap.company/v2';

    public function __construct(private readonly PaymentGateway $gateway) {}

    public function key(): string
    {
        return 'tap';
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        [$amount, $currency] = $this->chargeable($payment);
        $payment->forceFill(['charged_amount' => $amount, 'charged_currency' => $currency])->save();
        $owner = $payment->workspace?->owner;

        $response = $this->request()->post(self::BASE_URL.'/charges/', [
            'amount' => $amount,
            'currency' => $currency,
            'customer_initiated' => true,
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $this->description($payment),
            'metadata' => ['payment_id' => (string) $payment->id],
            'reference' => [
                'order' => (string) $payment->id,
                'transaction' => (string) ($payment->idempotency_key ?: 'payment-'.$payment->id),
                'idempotent' => (string) ($payment->idempotency_key ?: 'payment-'.$payment->id),
            ],
            'customer' => [
                'first_name' => mb_substr((string) ($owner?->name ?: 'Customer'), 0, 40),
                'email' => $owner?->email,
            ],
            'merchant' => ['id' => $this->gateway->credential('merchant_id')],
            'source' => ['id' => $this->gateway->credential('source_id', 'src_all')],
            'post' => ['url' => url('/webhooks/tap')],
            'redirect' => ['url' => $returnUrl],
        ])->throw()->json();

        $redirect = $response['transaction']['url'] ?? null;
        if (blank($response['id'] ?? null) || blank($redirect)) {
            throw new RuntimeException('تعذر إنشاء عملية الدفع لدى Tap.');
        }

        return new CheckoutSession($redirect, $response['id']);
    }

    public function verify(Payment $payment, array $callbackData): bool
    {
        $chargeId = $payment->external_id ?? ($callbackData['tap_id'] ?? null);
        if (blank($chargeId)) {
            return false;
        }

        $charge = $this->request()->get(self::BASE_URL.'/charges/'.$chargeId)->throw()->json();
        $valid = ($charge['status'] ?? null) === 'CAPTURED'
            && abs((float) ($charge['amount'] ?? 0) - (float) $payment->charged_amount) < 0.01
            && strtoupper((string) ($charge['currency'] ?? '')) === strtoupper((string) $payment->charged_currency)
            && (string) ($charge['reference']['order'] ?? '') === (string) $payment->id;

        if (! $valid) {
            $payment->forceFill(['failure_reason' => 'tap_verification_mismatch'])->save();

            return false;
        }

        $payment->forceFill(['external_capture_id' => $charge['id'], 'failure_reason' => null])->save();

        return true;
    }

    public function verifyWebhook(array $payload, ?string $postedHash): bool
    {
        if (blank($postedHash)) {
            return false;
        }

        $amount = number_format((float) ($payload['amount'] ?? 0), $this->decimals((string) ($payload['currency'] ?? 'SAR')), '.', '');
        $source = 'x_id'.($payload['id'] ?? '')
            .'x_amount'.$amount
            .'x_currency'.($payload['currency'] ?? '')
            .'x_gateway_reference'.($payload['reference']['gateway'] ?? '')
            .'x_payment_reference'.($payload['reference']['payment'] ?? '')
            .'x_status'.($payload['status'] ?? '')
            .'x_created'.($payload['transaction']['created'] ?? '');

        return hash_equals(hash_hmac('sha256', $source, (string) $this->gateway->credential('secret_key')), $postedHash);
    }

    public function healthCheck(): GatewayHealth
    {
        try {
            $response = $this->request()->get(self::BASE_URL.'/charges/health-check');
            $healthy = ! in_array($response->status(), [401, 403], true);

            return new GatewayHealth($healthy, $healthy ? 'اتصال Tap ناجح.' : 'رفض Tap بيانات الاتصال.');
        } catch (\Throwable) {
            return new GatewayHealth(false, 'تعذر الاتصال بـTap.');
        }
    }

    public function refund(Payment $payment, float $amount, string $reason): RefundResult
    {
        if (blank($payment->external_capture_id)) {
            return new RefundResult(false, message: 'لا توجد عملية Tap قابلة للاسترداد.');
        }

        $response = $this->request()->post(self::BASE_URL.'/refunds/', [
            'charge_id' => $payment->external_capture_id,
            'amount' => $amount,
            'currency' => $payment->charged_currency,
            'reason' => in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true) ? $reason : 'requested_by_customer',
            'post' => ['url' => url('/webhooks/tap')],
            'metadata' => ['payment_id' => (string) $payment->id],
        ]);

        if (! $response->successful()) {
            return new RefundResult(false, message: 'رفض Tap طلب الاسترداد.');
        }

        return new RefundResult(true, $response->json('id'), meta: $response->json() ?? []);
    }

    private function request()
    {
        $secret = $this->gateway->credential('secret_key');
        if (blank($secret)) {
            throw new RuntimeException('المفتاح السري لـTap غير مضبوط.');
        }

        return Http::withToken($secret)->withHeaders(['lang_code' => 'ar'])->acceptJson();
    }

    private function chargeable(Payment $payment): array
    {
        $currency = strtoupper($this->gateway->currency ?: $payment->currency);
        $rate = $currency === strtoupper($payment->currency) ? 1 : (float) ($this->gateway->fx_rate ?: 1);

        return [round($payment->amount * $rate, $this->decimals($currency)), $currency];
    }

    private function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), ['BHD', 'KWD', 'OMR'], true) ? 3 : 2;
    }

    private function description(Payment $payment): string
    {
        return $payment->purpose === 'plan'
            ? 'اشتراك '.($payment->plan?->name ?? '#'.$payment->id)
            : 'حزمة رصيد '.($payment->creditPack?->name ?? '#'.$payment->id);
    }
}
