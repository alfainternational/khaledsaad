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

        // بوابة يدوية مفعّلة افتراضيًا: الشراء يعمل، والآدمن يضيف بوابة حقيقية لاحقًا.
        PaymentGateway::updateOrCreate(
            ['provider' => 'manual'],
            [
                'label' => 'تحويل يدوي / بنكي',
                'mode' => 'live',
                'is_active' => true,
                'credentials' => [],
                'sort_order' => 99,
            ],
        );
    }
}
