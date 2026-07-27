<?php

namespace Database\Seeders;

use App\Models\CreditPack;
use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

/**
 * حزم أرصدة افتراضية + بوابة يدوية مفعّلة، فيكون تدفّق الشراء عاملًا فورًا
 * قبل أن يضيف الآدمن بوابة حقيقية (PayPal وغيرها) من اللوحة.
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $packs = [
            ['name' => 'حزمة صغيرة', 'credits' => 20, 'price' => 25, 'currency' => 'SAR', 'sort_order' => 1],
            ['name' => 'حزمة متوسطة', 'credits' => 60, 'price' => 60, 'currency' => 'SAR', 'sort_order' => 2],
            ['name' => 'حزمة كبيرة', 'credits' => 150, 'price' => 130, 'currency' => 'SAR', 'sort_order' => 3],
        ];

        foreach ($packs as $pack) {
            CreditPack::updateOrCreate(['name' => $pack['name']], $pack + ['is_active' => true]);
        }

        // PayPal هي البوابة الأساسية: صفّها جاهز بانتظار المفاتيح من اللوحة.
        // تبقى معطّلة حتى تكتمل مفاتيحها، فلا تُعرض بوابة لا تعمل.
        PaymentGateway::firstOrCreate(
            ['provider' => 'paypal'],
            [
                'label' => 'PayPal (بطاقة أو محفظة)',
                'mode' => 'test',
                'is_active' => false,
                'credentials' => [],
                'currency' => 'USD',
                'fx_rate' => 0.2667, // الريال السعودي مربوط بالدولار: 1 ÷ 3.75
                'sort_order' => 1,
            ],
        );

        // بوابة يدوية للتحويل البنكي: كل دفعة تبقى معلّقة حتى يعتمدها الآدمن.
        PaymentGateway::firstOrCreate(
            ['provider' => 'manual'],
            [
                'label' => 'تحويل بنكي',
                'mode' => 'live',
                'is_active' => true,
                'credentials' => [],
                'instructions' => 'حوّل المبلغ إلى حسابنا البنكي ثم أرسل صورة الإشعار، ويُضاف رصيدك فور التأكد من التحويل.',
                'sort_order' => 99,
            ],
        );

        PaymentGateway::firstOrCreate(
            ['provider' => 'moyasar'],
            [
                'label' => 'ميسّر (بطاقات ومدى وApple Pay)', 'mode' => 'test',
                'is_active' => false, 'credentials' => [], 'currency' => 'SAR',
                'fx_rate' => 1, 'sort_order' => 2,
            ],
        );

        PaymentGateway::firstOrCreate(
            ['provider' => 'tap'],
            [
                'label' => 'Tap Payments', 'mode' => 'test',
                'is_active' => false, 'credentials' => [], 'currency' => 'SAR',
                'fx_rate' => 1, 'sort_order' => 3,
            ],
        );

        // الترحيل يضيف مفهوم «الافتراضية» للبيانات القائمة. إن لم يحدد
        // الآدمن واحدة بعد، نعتمد أول بوابة مفعلة دون تعطيل أي بوابة أخرى.
        if (! PaymentGateway::where('is_default', true)->exists()) {
            PaymentGateway::where('is_active', true)->orderBy('sort_order')->first()
                ?->update(['is_default' => true]);
        }
    }
}
