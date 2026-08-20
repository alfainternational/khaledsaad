<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * الخطط من وثيقة المنتج. الأسعار للاختبار لا للاعتماد النهائي.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => 'free', 'name' => config('catalogue.plans.free', 'مجانية'), 'price' => 0, 'monthly_credits' => 5,
                'project_limit' => 1, 'sort_order' => 1,
                'features' => ['أداة تشخيص واحدة', 'تقرير مختصر', 'مشروع واحد'],
            ],
            [
                'key' => 'individual', 'name' => config('catalogue.plans.individual', 'فردية'), 'price' => 49, 'monthly_credits' => 40,
                'project_limit' => 3, 'sort_order' => 2,
                'features' => ['كل الأدوات', 'تصدير PDF', 'ثلاثة مشاريع', 'بلا إعلانات'],
            ],
            [
                'key' => 'professional', 'name' => config('catalogue.plans.professional', 'احترافية'), 'price' => 129, 'monthly_credits' => 150,
                'project_limit' => 10, 'sort_order' => 3,
                'features' => ['تقارير متقدمة', 'مقارنة زمنية', 'عشرة مشاريع', 'أولوية المعالجة'],
            ],
            [
                'key' => 'team', 'name' => config('catalogue.plans.team', 'فرق'), 'price' => 299, 'monthly_credits' => 500,
                'project_limit' => 20, 'sort_order' => 4,
                'features' => ['20 مشروعًا', 'مؤشرات مشتركة', 'رصيد موسّع'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['key' => $plan['key']], $plan);
        }
    }
}
