<?php

namespace App\Services\Billing;

use App\Domain\Billing\Models\PayPalWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $baseUrl;

    public function __construct()
    {
        $mode = config('services.paypal.mode', 'sandbox');
        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function isConfigured(): bool
    {
        $clientId = config('services.paypal.client_id');
        $secret = config('services.paypal.client_secret');

        if (! filled($clientId) || ! filled($secret)) {
            return false;
        }

        $flag = config('services.paypal.enabled');

        // لم يُضبط PAYPAL_ENABLED في .env → لا نعطل الدفع إذا وُجدت المفاتيح
        if ($flag === null || $flag === '') {
            return true;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    public function getAccessToken(): string
    {
        $response = Http::withBasicAuth(
            (string) config('services.paypal.client_id'),
            (string) config('services.paypal.client_secret'),
        )
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            throw new \RuntimeException('PayPal: فشل الحصول على رمز الوصول: '.$response->body());
        }

        return (string) $response->json('access_token');
    }

    /**
     * معرّف خطة الاشتراك في PayPal (Billing Plan ID) لدورة محددة.
     */
    public function resolveBillingPlanId(string $monthlyId, string $annualId, string $billingCycle): string
    {
        $id = $billingCycle === 'annual' ? $annualId : $monthlyId;

        if ($id === '' || $id === '0') {
            throw new \InvalidArgumentException('خطة PayPal غير مربوطة لهذه الدورة. أضف المعرف في لوحة الإدارة أو في ملف البيئة.');
        }

        return $id;
    }

    /**
     * قائمة خطط الفوترة (لنسخ معرّفات P- إلى .env أو لوحة الإدارة).
     *
     * @return array<string, mixed>
     */
    public function listBillingPlans(int $page = 1, int $pageSize = 20): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)->get("{$this->baseUrl}/v1/billing/plans", [
            'page' => $page,
            'page_size' => $pageSize,
            'total_required' => 'true',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('PayPal: تعذر جلب قائمة الخطط: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array{subscription_id: string, approval_url: string|null}
     */
    public function createSubscription(string $paypalPlanId, string $returnUrl, string $cancelUrl): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)->post("{$this->baseUrl}/v1/billing/subscriptions", [
            'plan_id' => $paypalPlanId,
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('PayPal: فشل إنشاء الاشتراك: '.$response->body());
        }

        $data = $response->json();
        $approvalUrl = collect($data['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'subscription_id' => (string) ($data['id'] ?? ''),
            'approval_url' => $approvalUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $subscriptionId): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)->get("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}");

        if (! $response->successful()) {
            throw new \RuntimeException('PayPal: تعذر قراءة الاشتراك.');
        }

        return $response->json();
    }

    public function cancelSubscription(string $subscriptionId, string $reason = 'Cancelled by user'): bool
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)->post(
            "{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/cancel",
            ['reason' => $reason]
        );

        return $response->status() === 204;
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        if (! filled($webhookId)) {
            Log::warning('PayPal: PAYPAL_WEBHOOK_ID غير مضبوط — رفض التحقق في الإنتاج.');

            return (bool) config('app.debug');
        }

        try {
            $token = $this->getAccessToken();
            $response = Http::withToken($token)->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
                'auth_algo' => $headers['paypal-auth-algo'] ?? '',
                'cert_url' => $headers['paypal-cert-url'] ?? '',
                'transmission_id' => $headers['paypal-transmission-id'] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($body, true),
            ]);

            return $response->json('verification_status') === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verify: '.$e->getMessage());

            return false;
        }
    }

    public function isEventProcessed(string $eventId): bool
    {
        return PayPalWebhookEvent::query()->where('event_id', $eventId)->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(string $eventId, string $eventType, ?string $resourceId, array $payload): void
    {
        PayPalWebhookEvent::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'resource_id' => $resourceId,
            'payload' => $payload,
        ]);
    }
}
