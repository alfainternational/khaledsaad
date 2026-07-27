<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Models\PaymentGateway;
use RuntimeException;

/**
 * يحوّل صف payment_gateways إلى مزوّد ملموس.
 *
 * الفهرس هنا لا يعلن إلا ما نملك له صنفًا يعمل. بوابة معلنة بلا تنفيذ تُنشئ
 * صفًّا يُفعّله الآدمن ثم ينفجر عند أول عملية شراء — فلا نعلن ما لا ننفّذ.
 * إضافة بوابة = صنف يحقق العقد + سطر هنا.
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentProvider>>
     */
    private const PROVIDERS = [
        'paypal' => PayPalProvider::class,
        'moyasar' => MoyasarProvider::class,
        'tap' => TapProvider::class,
        'manual' => ManualProvider::class,
    ];

    /**
     * أنواع البوابات المتاحة للإضافة من اللوحة ومفاتيح كل نوع.
     * required = ما لا تعمل البوابة بدونه.
     *
     * @return array<string, array{label: string, fields: array<int, string>, required: array<int, string>, hint: string}>
     */
    public static function catalogue(): array
    {
        return [
            'paypal' => [
                'label' => 'PayPal',
                'fields' => ['client_id', 'secret', 'webhook_id'],
                'required' => ['client_id', 'secret'],
                'hint' => 'المفاتيح من PayPal Developer. webhook_id يُنشأ بعد تسجيل رابط الإشعار، وبدونه لن تُقبل إشعارات PayPal.',
            ],
            'moyasar' => [
                'label' => 'Moyasar (ميسّر)',
                'fields' => ['secret_key', 'webhook_secret'],
                'required' => ['secret_key', 'webhook_secret'],
                'hint' => 'استخدم المفتاح السري من لوحة Moyasar، وأنشئ سرًا مشتركًا للإشعارات. الدفع يتم في صفحة Moyasar المستضافة ولا تمر بيانات البطاقة بخادم المنصة.',
            ],
            'tap' => [
                'label' => 'Tap Payments',
                'fields' => ['secret_key', 'merchant_id'],
                'required' => ['secret_key', 'merchant_id'],
                'hint' => 'المفتاح السري ومعرّف التاجر من Tap. يستخدم الخادم صفحة Tap المستضافة ويتحقق من العملية والإشعار قبل التفعيل.',
            ],
            'manual' => [
                'label' => 'تحويل يدوي / بنكي',
                'fields' => [],
                'required' => [],
                'hint' => 'اكتب بيانات الحساب في «تعليمات الدفع». كل دفعة تبقى معلّقة حتى تعتمدها من سجل المدفوعات.',
            ],
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
        return $this->defaultGateway();
    }

    public function activeGateways()
    {
        return PaymentGateway::query()->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('sort_order')->get()
            ->filter(fn (PaymentGateway $gateway) => $gateway->hasRequiredCredentials())
            ->values();
    }

    public function defaultGateway(): ?PaymentGateway
    {
        return $this->activeGateways()->first();
    }

    public function activeGatewayById(int $id): ?PaymentGateway
    {
        return $this->activeGateways()->firstWhere('id', $id);
    }

    public function activeProvider(): ?PaymentProvider
    {
        $gateway = $this->activeGateway();

        return $gateway !== null ? $this->provider($gateway) : null;
    }

    public function hasActiveGateway(): bool
    {
        return $this->activeGateways()->isNotEmpty();
    }

    /**
     * بوابة مزوّد بعينه (حتى لو لم تكن المفعّلة) — تُستخدم في الإشعارات
     * وعند عودة دفعة بدأت قبل تبديل البوابة.
     */
    public function gatewayFor(string $provider): ?PaymentGateway
    {
        return PaymentGateway::where('provider', $provider)->first();
    }
}
