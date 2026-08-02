<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ربط بوابة PayPal من الطرفية بدل لوحة الآدمن.
 *
 * لماذا أمر لا شاشة: نفس البوابة تُضبط في نسختين (المحلية والاستضافة)،
 * والاستضافة لا تُفتح لوحتها إلا بمتصفح وجلسة. الأمر يعطي مسارًا واحدًا
 * يعمل في الاثنين ويترك أثرًا يمكن تكراره.
 *
 * السر لا يُقبل خيارًا في سطر الأوامر مطلقًا — يُسأل عنه مخفيًّا فقط،
 * لأن الخيار يُسجَّل في تاريخ الصدفة وفي قائمة العمليات، وسرّ PayPal
 * الحيّ المسرَّب يعني مالًا يُحصَّل باسمك.
 *
 * ولا تُفعَّل بوابة حيّة قبل أن ينجح فحص اتصالها: التفعيل بمفاتيح خاطئة
 * يعني عميلًا يضغط «ادفع» فينفجر أمامه.
 */
class ConnectPayPalGateway extends Command
{
    protected $signature = 'payments:paypal-connect
        {--client-id= : معرّف التطبيق (غير سرّي — يُسأل عنه إن غاب)}
        {--webhook-id= : معرّف الإشعار من PayPal Developer}
        {--mode=live : live أو test}
        {--currency=USD : عملة التحصيل — PayPal لا يقبل SAR}
        {--fx-rate=0.2667 : معامل التحويل من عملة الأسعار}
        {--keep-inactive : اضبط المفاتيح دون تفعيل البوابة}';

    protected $description = 'Store PayPal live credentials, verify them, and activate the gateway';

    public function handle(PaymentGatewayManager $manager): int
    {
        $mode = $this->option('mode') === 'test' ? 'test' : 'live';

        $clientId = trim((string) ($this->option('client-id') ?: $this->ask('Client ID')));

        // secret() لا يعرض ما يُكتب ولا يُخزَّن في تاريخ الأوامر.
        $secret = trim((string) $this->secret('Secret (لن يظهر أثناء الكتابة)'));

        $webhookId = trim((string) ($this->option('webhook-id')
            ?: $this->ask('Webhook ID (اتركه فارغًا للإبقاء على المحفوظ)', '')));

        if (blank($clientId) || blank($secret)) {
            $this->error('Client ID والسر إلزاميان — لا تعمل البوابة بدونهما.');

            return self::FAILURE;
        }

        $gateway = PaymentGateway::firstOrNew(['provider' => 'paypal']);

        // الإبقاء على webhook_id المحفوظ حين لا يُمرَّر جديد: إعادة تشغيل
        // الأمر لتصحيح مفتاح يجب ألّا تُسقط الإشعارات صامتةً.
        $credentials = array_filter([
            'client_id' => $clientId,
            'secret' => $secret,
            'webhook_id' => $webhookId ?: $gateway->credential('webhook_id'),
        ], fn (?string $value) => filled($value));

        $gateway->fill([
            'label' => $gateway->label ?: 'PayPal (بطاقة أو محفظة)',
            'mode' => $mode,
            'currency' => strtoupper((string) $this->option('currency')),
            'fx_rate' => (float) $this->option('fx-rate'),
            'credentials' => $credentials,
            'sort_order' => $gateway->sort_order ?: 1,
        ])->save();

        $this->line("خُزّنت المفاتيح مشفّرة (بوابة #{$gateway->id}, وضع {$mode}).");

        if (blank($credentials['webhook_id'] ?? null)) {
            $this->warn('بلا webhook_id تُرفض إشعارات PayPal، فيصل الرصيد فقط عند عودة العميل للموقع.');
        }

        // الفحص يستدعي PayPal فعليًّا — فشله هنا أرخص من فشله عند أول عميل.
        try {
            $health = $manager->provider($gateway)->healthCheck();
        } catch (\Throwable $exception) {
            $health = null;
            $this->error('فشل فحص الاتصال: '.$exception->getMessage());
        }

        $gateway->update([
            'health_status' => $health?->healthy ? 'healthy' : 'unhealthy',
            'last_health_check_at' => now(),
            'last_health_message' => $health?->message ?? 'تعذّر الاتصال بـ PayPal.',
        ]);

        if (! $health?->healthy) {
            $this->error('لم تُفعَّل البوابة: المفاتيح لم تُقبل لدى PayPal في وضع '.$mode.'.');

            return self::FAILURE;
        }

        $this->info('✓ '.$health->message);

        if ($this->option('keep-inactive')) {
            $this->line('تُركت البوابة معطّلة بناءً على --keep-inactive.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($gateway): void {
            PaymentGateway::where('id', '!=', $gateway->id)->update(['is_default' => false]);
            $gateway->update(['is_active' => true, 'is_default' => true]);
        });

        $this->info('✓ فُعّلت بوابة PayPal وأصبحت الافتراضية. الدفع متاح الآن.');

        return self::SUCCESS;
    }
}
