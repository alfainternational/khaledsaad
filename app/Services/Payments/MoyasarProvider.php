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

class MoyasarProvider implements PaymentProvider
{
    private const BASE_URL = 'https://api.moyasar.com/v1';

    public function __construct(private readonly PaymentGateway $gateway) {}

    public function key(): string
    {
        return 'moyasar';
    }

    public function createCheckout(Payment $payment, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        [$amount, $currency] = $this->chargeable($payment);
        $payment->forceFill(['charged_amount' => $amount, 'charged_currency' => $currency])->save();

        $response = $this->request()->post(self::BASE_URL.'/invoices', [
            'amount' => $this->toMinor($amount, $currency),
            'currency' => $currency,
            'description' => $this->description($payment),
            'callback_url' => url('/webhooks/moyasar'),
            'success_url' => $returnUrl,
            'back_url' => $cancelUrl,
            'expired_at' => now()->addHour()->toISOString(),
            'metadata' => ['payment_id' => (string) $payment->id],
        ])->throw()->json();

        if (blank($response['id'] ?? null) || blank($response['url'] ?? null)) {
            throw new RuntimeException('تعذر إنشاء فاتورة الدفع لدى Moyasar.');
        }

        return new CheckoutSession($response['url'], $response['id']);
    }

    public function verify(Payment $payment, array $callbackData): bool
    {
        $invoiceId = $payment->external_id ?? ($callbackData['id'] ?? null);
        if (blank($invoiceId)) {
            return false;
        }

        $invoice = $this->request()->get(self::BASE_URL.'/invoices/'.$invoiceId)->throw()->json();
        $minor = $this->toMinor((float) $payment->charged_amount, (string) $payment->charged_currency);
        if (($invoice['status'] ?? null) !== 'paid'
            || (int) ($invoice['amount'] ?? 0) !== $minor
            || strtoupper((string) ($invoice['currency'] ?? '')) !== strtoupper((string) $payment->charged_currency)) {
            $payment->forceFill(['failure_reason' => 'moyasar_verification_mismatch'])->save();

            return false;
        }

        $paid = collect($invoice['payments'] ?? [])->first(fn ($item) => in_array($item['status'] ?? null, ['paid', 'captured'], true));
        if (! is_array($paid) || (int) ($paid['amount'] ?? 0) !== $minor) {
            return false;
        }

        $payment->forceFill(['external_capture_id' => $paid['id'] ?? null, 'failure_reason' => null])->save();

        return true;
    }

    public function healthCheck(): GatewayHealth
    {
        try {
            $response = $this->request()->get(self::BASE_URL.'/invoices', ['page' => 1]);

            return new GatewayHealth($response->successful(), $response->successful() ? 'اتصال Moyasar ناجح.' : 'رفض Moyasar بيانات الاتصال.');
        } catch (\Throwable) {
            return new GatewayHealth(false, 'تعذر الاتصال بـMoyasar.');
        }
    }

    public function refund(Payment $payment, float $amount, string $reason): RefundResult
    {
        if (blank($payment->external_capture_id)) {
            return new RefundResult(false, message: 'لا توجد عملية Moyasar قابلة للاسترداد.');
        }

        $response = $this->request()->post(self::BASE_URL.'/payments/'.$payment->external_capture_id.'/refund', [
            'amount' => $this->toMinor($amount, (string) $payment->charged_currency),
        ]);

        if (! $response->successful()) {
            return new RefundResult(false, message: 'رفض Moyasar طلب الاسترداد.');
        }

        return new RefundResult(true, $response->json('id') ?? $payment->external_capture_id, meta: $response->json() ?? []);
    }

    private function request()
    {
        $secret = $this->gateway->credential('secret_key');
        if (blank($secret)) {
            throw new RuntimeException('المفتاح السري لـMoyasar غير مضبوط.');
        }

        return Http::withBasicAuth($secret, '')->acceptJson();
    }

    private function chargeable(Payment $payment): array
    {
        $currency = strtoupper($this->gateway->currency ?: $payment->currency);
        $rate = $currency === strtoupper($payment->currency) ? 1 : (float) ($this->gateway->fx_rate ?: 1);

        return [round($payment->amount * $rate, $this->decimals($currency)), $currency];
    }

    private function toMinor(float $amount, string $currency): int
    {
        return (int) round($amount * (10 ** $this->decimals($currency)));
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
