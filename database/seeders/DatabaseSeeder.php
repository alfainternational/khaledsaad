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
            PaymentSeeder::class,
            ToolCatalogSeeder::class,
            // حساب الآدمن يبقى عبر عمليات إعادة التهيئة.
            AdminUserSeeder::class,
        ]);
    }
}
