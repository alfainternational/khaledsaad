<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            // الميزات بعد الخطط: توزيعها يحتاج الخطط موجودة.
            FeatureSeeder::class,
            PaymentSeeder::class,
            ToolCatalogSeeder::class,
            ConsultationCatalogSeeder::class,
            // حساب الآدمن يبقى عبر عمليات إعادة التهيئة.
            AdminUserSeeder::class,
        ]);
    }
}
