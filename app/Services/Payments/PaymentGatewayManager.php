<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Models\PaymentGateway;
use RuntimeException;

/**
 * يحوّل صف payment_gateways إلى مزوّد ملموس.
 *
 * إضافة بوابة = سطر في map + صنف يحقق العقد. المفاتيح كلها من قاعدة البيانات.
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentProvider>>
     */
    private const PROVIDERS = [
        'paypal' => PayPalProvider::class,
        'manual' => ManualProvider::class,
    ];

    /**
     * أنواع البوابات المتاحة للإضافة من اللوحة، ومفاتيح كل نوع.
     *
     * @return array<string, array{label: string, fields: array<int, string>}>
     */
    public static function catalogue(): array
    {
        return [
            'paypal' => ['label' => 'PayPal', 'fields' => ['client_id', 'secret']],
            'moyasar' => ['label' => 'Moyasar', 'fields' => ['api_key', 'secret_key']],
            'tap' => ['label' => 'Tap', 'fields' => ['secret_key', 'public_key']],
            'manual' => ['label' => 'تحويل يدوي / بنكي', 'fields' => []],
        ];
    }

    public function provider(PaymentGateway $gateway): PaymentProvider
    {
        $class = self::PROVIDERS[$gateway->provider]
            ?? throw new RuntimeException("مزوّد الدفع {$gateway->provider} غير مدعوم بعد.");

        return new $class($gateway);
    }

    /**
     * البوابة المفعّلة الحالية، أو null إن لم تُفعَّل أي بوابة.
     */
    public function activeGateway(): ?PaymentGateway
    {
        return PaymentGateway::where('is_active', true)->orderBy('sort_order')->first();
    }

    public function activeProvider(): ?PaymentProvider
    {
        $gateway = $this->activeGateway();

        return $gateway !== null ? $this->provider($gateway) : null;
    }

    public function hasActiveGateway(): bool
    {
        return $this->activeGateway() !== null;
    }
}
