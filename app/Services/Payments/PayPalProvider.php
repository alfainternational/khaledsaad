<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Contracts\Payments\GatewayHealth;
use App\Contracts\Payments\PaymentProvider;
use App\Contracts\Payments\RefundResult;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PayPal عبر Orders API v2 — بوابتنا الأساسية.
 *
 * لماذا PayPal أساسية: العميل يدفع ببطاقته أو من رصيد محفظته، وفي الحالتين
 * نستقبل نفس الالتقاط (capture) فلا نحتاج مسارين.
 *
 * ثلاث حقائق تحكم هذا الصنف:
 * 1) المفاتيح من جدول payment_gateways المشفّر لا من .env — يضيفها الآدمن
 *    ويبدّل بين sandbox/live بضغطة.
 * 2) PayPal لا يقبل الريال السعودي. أسعارنا بالريال، فنحوّلها إلى عملة
 *    البوابة (USD افتراضًا) بمعامل يضبطه الآدمن، ونسجّل المبلغ المحصَّل فعلًا.
 * 3) لا نصدّق العودة من المتصفح وحدها: نلتقط الطلب ونطابق مبلغه وعملته
 *    قبل منح أي رصيد، ونقبل الإشعار (webhook) موقّعًا كمسار ثانٍ مضمون.
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
        [$amount, $currency] = $this->chargeable($payment);

        // نحفظ ما سيُحصَّل فعلًا قبل الذهاب للبوابة، ليُطابَق عند العودة.
        $payment->forceFill([
            'charged_amount' => $amount,
            'charged_currency' => $currency,
        ])->save();

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $payment->id,
                    // custom_id يعود إلينا في الإشعار فنعرف أي دفعة يخصّ.
                    'custom_id' => (string) $payment->id,
                    'description' => $this->describe($payment),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    'brand_name' => config('app.name'),
                    // BCP 47 بشرطة لا بشرطة سفلية: ar_SA يرفضه PayPal بـ400
                    // INVALID_PARAMETER_SYNTAX فينكسر الشراء عند أول عميل.
                    'locale' => 'ar-SA',
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

    /**
     * لا يُمنح رصيد إلا إذا رجع من PayPal التقاط مكتمل بنفس المبلغ والعملة.
     */
    public function verify(Payment $payment, array $callbackData): bool
    {
        $orderId = $payment->external_id ?? ($callbackData['token'] ?? null);

        if ($orderId === null) {
            return false;
        }

        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            // مفتاح تكرار: التقاط الطلب نفسه مرتين لا يخصم مرتين.
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$payment->id])
            ->post($this->baseUrl()."/v2/checkout/orders/{$orderId}/capture");

        $body = $response->json() ?? [];

        // طلب سبق التقاطه ليس فشلًا: نقرأ حالته بدل رفضه.
        if (! $response->successful()) {
            $issue = $body['details'][0]['issue'] ?? null;

            if ($issue === 'ORDER_ALREADY_CAPTURED') {
                $body = Http::withToken($token)->acceptJson()
                    ->get($this->baseUrl()."/v2/checkout/orders/{$orderId}")
                    ->json() ?? [];
            } else {
                Log::warning('PayPal capture failed', ['payment' => $payment->id, 'issue' => $issue]);
                $payment->forceFill(['failure_reason' => $issue ?? 'capture_failed'])->save();

                return false;
            }
        }

        if (($body['status'] ?? null) !== 'COMPLETED') {
            $payment->forceFill(['failure_reason' => 'status:'.($body['status'] ?? 'unknown')])->save();

            return false;
        }

        $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
        $paidValue = (float) ($capture['amount']['value'] ?? 0);
        $paidCurrency = $capture['amount']['currency_code'] ?? null;

        if (! $this->matchesExpected($payment, $paidValue, $paidCurrency)) {
            Log::warning('PayPal amount mismatch', [
                'payment' => $payment->id, 'paid' => $paidValue, 'currency' => $paidCurrency,
            ]);
            $payment->forceFill(['failure_reason' => 'amount_mismatch'])->save();

            return false;
        }

        $payment->forceFill([
            'external_capture_id' => $capture['id'] ?? null,
            'charged_amount' => $paidValue,
            'charged_currency' => $paidCurrency,
            'failure_reason' => null,
        ])->save();

        return true;
    }

    /**
     * التحقق من توقيع الإشعار لدى PayPal نفسه — لا نثق بجسم الطلب وحده.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $event
     */
    public function verifyWebhook(array $headers, array $event): bool
    {
        $webhookId = $this->gateway->credential('webhook_id');

        if (blank($webhookId)) {
            // بلا معرّف إشعار لا يمكن التحقق، ورفض الإشعار أأمن من قبوله.
            return false;
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['paypal-auth-algo'] ?? '',
                'cert_url' => $headers['paypal-cert-url'] ?? '',
                'transmission_id' => $headers['paypal-transmission-id'] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ])
            ->json();

        return ($response['verification_status'] ?? null) === 'SUCCESS';
    }

    public function healthCheck(): GatewayHealth
    {
        try {
            $this->accessToken();

            return new GatewayHealth(true, 'اتصال PayPal ناجح.');
        } catch (\Throwable) {
            return new GatewayHealth(false, 'تعذر التحقق من مفاتيح PayPal.');
        }
    }

    public function refund(Payment $payment, float $amount, string $reason): RefundResult
    {
        if (blank($payment->external_capture_id)) {
            return new RefundResult(false, message: 'لا يوجد معرّف تحصيل صالح للاسترداد.');
        }

        $response = Http::withToken($this->accessToken())->acceptJson()
            ->withHeaders(['PayPal-Request-Id' => 'refund-'.$payment->id.'-'.number_format($amount, 2, '.', '')])
            ->post($this->baseUrl().'/v2/payments/captures/'.$payment->external_capture_id.'/refund', [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $payment->charged_currency,
                ],
                'note_to_payer' => mb_substr($reason, 0, 255),
            ]);

        if (! $response->successful()) {
            return new RefundResult(false, message: 'رفض PayPal طلب الاسترداد.');
        }

        return new RefundResult(true, $response->json('id'), meta: $response->json() ?? []);
    }

    /**
     * المبلغ بعملة البوابة: سعرنا بالريال × معامل التحويل الذي يضبطه الآدمن.
     *
     * @return array{0: float, 1: string}
     */
    private function chargeable(Payment $payment): array
    {
        $currency = $this->gateway->currency ?: 'USD';
        $rate = (float) ($this->gateway->fx_rate ?: 1);

        if ($rate <= 0) {
            throw new RuntimeException('معامل تحويل العملة في بوابة PayPal غير صالح.');
        }

        // العملة نفسها = لا تحويل، مهما كان المعامل المسجَّل.
        if (strtoupper($currency) === strtoupper($payment->currency)) {
            return [round((float) $payment->amount, 2), strtoupper($payment->currency)];
        }

        return [round($payment->amount * $rate, 2), strtoupper($currency)];
    }

    private function matchesExpected(Payment $payment, float $paid, ?string $currency): bool
    {
        $expected = (float) ($payment->charged_amount ?? 0);

        if ($expected <= 0 || $currency === null) {
            return false;
        }

        // فرق الكسور في التقريب مسموح، وما دونه لا.
        return abs($paid - $expected) < 0.01
            && strtoupper($currency) === strtoupper((string) $payment->charged_currency);
    }

    private function describe(Payment $payment): string
    {
        return $payment->purpose === 'plan'
            ? 'اشتراك: '.($payment->plan?->name ?? 'خطة')
            : 'رصيد: '.($payment->creditPack?->name ?? 'حزمة');
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
