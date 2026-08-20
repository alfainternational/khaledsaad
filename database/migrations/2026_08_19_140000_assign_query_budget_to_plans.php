<?php

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Support\Billing\FeatureKey;
use Illuminate\Database\Migrations\Migration;

/**
 * ربط ميزانية الاستعلامات بالباقات على البيئات القائمة.
 *
 * لا يُشغَّل `FeatureSeeder` لهذا: بذره يستخدم `updateOrCreate` على كل مفاتيح
 * المصفوفة، فيمسح ما ضبطه الآدمن من اللوحة لباقات موجودة ويعيدها للافتراضي.
 * الهجرة تكتب عنصر الميزانية وحده، ولا تلمس صفًّا موجودًا — فإن كان الآدمن قد
 * ضبط رقمًا بنفسه بقي رقمه.
 *
 * الأرقام معايَرة على دورة الاستطلاع: ٥ أسئلة × ٣ محاولات = ١٥ استعلامًا (§٤.٢).
 */
return new class extends Migration
{
    /** @var array<string, int> */
    private const BUDGETS = [
        'free' => 150,          // عشر دورات استطلاع: يكفي لرؤية الفجوة (§٦)
        'individual' => 600,    // أربعون دورة على ثلاثة مشاريع
        'professional' => 2000, // على عشرة مشاريع
        'team' => 6000,         // على عشرين مشروعًا
    ];

    public function up(): void
    {
        $feature = Feature::updateOrCreate(
            ['key' => FeatureKey::QUERY_BUDGET_MONTHLY],
            [
                'name' => 'ميزانية استعلامات الذكاء',
                'description' => 'سقف استعلامات النماذج شهريًا لكل مساحة عمل. دورة استطلاع واحدة = ١٥ استعلامًا (٥ أسئلة × ٣ محاولات).',
                'group' => 'growth',
                'type' => Feature::TYPE_QUOTA,
                'unit' => 'استعلام/شهر',
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => true,
                'default_value' => 150,
                'is_active' => true,
                'sort_order' => 115,
            ],
        );

        foreach (self::BUDGETS as $planKey => $value) {
            $plan = Plan::where('key', $planKey)->first();

            if ($plan === null) {
                continue;
            }

            // firstOrCreate لا updateOrCreate: رقم ضبطه الآدمن يبقى له.
            PlanFeature::firstOrCreate(
                ['plan_id' => $plan->id, 'feature_id' => $feature->id],
                ['enabled' => true, 'value' => $value, 'sort_order' => $feature->sort_order],
            );
        }
    }

    public function down(): void
    {
        $feature = Feature::where('key', FeatureKey::QUERY_BUDGET_MONTHLY)->first();

        if ($feature === null) {
            return;
        }

        PlanFeature::where('feature_id', $feature->id)->delete();
        $feature->delete();
    }
};
