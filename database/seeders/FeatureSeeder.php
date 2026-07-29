<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Support\Billing\FeatureKey;
use Illuminate\Database\Seeder;

/**
 * فهرس الميزات + توزيعها على الخطط.
 *
 * كل عنصر هنا له نقطة منع حقيقية في الكود (انظر FeatureKey)، عدا ما وُسم
 * display فهو وعد خدمة بشري لا بوابة تقنية — ونقولها صراحة في اللوحة.
 */
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogue() as $index => $feature) {
            Feature::updateOrCreate(
                ['key' => $feature['key']],
                $feature + ['sort_order' => ($index + 1) * 10, 'is_active' => true],
            );
        }

        $this->assignToPlans();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        return [
            [
                'key' => FeatureKey::PROJECTS_LIMIT,
                'name' => 'المشاريع',
                'description' => 'عدد المشاريع التي يمكن إنشاؤها في مساحة العمل.',
                'group' => 'core', 'type' => Feature::TYPE_LIMIT, 'unit' => 'مشروع',
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => true, 'default_value' => 1,
            ],
            [
                'key' => FeatureKey::TOOL_RUNS_MONTHLY,
                'name' => 'تشغيل الأدوات',
                'description' => 'عدد تشغيلات الأدوات في الشهر الجاري (بخلاف الرصيد).',
                'group' => 'core', 'type' => Feature::TYPE_QUOTA, 'unit' => 'تشغيل/شهر',
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => true, 'default_value' => null,
            ],
            [
                'key' => FeatureKey::COMPETITORS_LIMIT,
                'name' => 'المنافسون',
                'description' => 'عدد المنافسين المتابَعين لكل مشروع.',
                'group' => 'core', 'type' => Feature::TYPE_LIMIT, 'unit' => 'منافس/مشروع',
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => true, 'default_value' => 3,
            ],
            [
                'key' => FeatureKey::KPI_TRACKING,
                'name' => 'تتبّع مؤشرات الأداء',
                'description' => 'تسجيل مؤشرات ومتابعة تطوّرها زمنيًا.',
                'group' => 'core', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => FeatureKey::DIAGNOSIS_FULL,
                'name' => 'التشخيص الكامل',
                'description' => 'تاريخ درجة النضج وتصدير بطاقة الجاهزية وتقرير الزحف.',
                'group' => 'core', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => FeatureKey::REPORTS_PDF,
                'name' => 'تصدير PDF',
                'description' => 'تنزيل التقرير ملفًا جاهزًا للمشاركة.',
                'group' => 'reports', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => FeatureKey::REPORTS_AGENCY,
                'name' => 'تقرير الوكالة الموحّد',
                'description' => 'تقرير شامل يجمع مخرجات المشروع في مستند واحد.',
                'group' => 'reports', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => FeatureKey::MANUAL_REVIEW,
                'name' => 'مراجعة بشرية',
                'description' => 'إحالة التشغيل لمراجعة خبير بدل المسار الآلي.',
                'group' => 'reports', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => FeatureKey::GROWTH_PULSE,
                'name' => 'نبض النمو',
                'description' => 'ملخّص دوري بما تغيّر وما يستحق التحرّك.',
                'group' => 'growth', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => FeatureKey::GROWTH_GEO,
                'name' => 'حزمة الظهور للآلات (GEO)',
                'description' => 'ملف llms.txt وبيانات تجعل المشروع مقروءًا لمحرّكات الذكاء.',
                'group' => 'growth', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => FeatureKey::WATCHERS_LIMIT,
                'name' => 'التقارير الحيّة',
                'description' => 'عدد التقارير التي تُتابَع وتُحدَّث تلقائيًا.',
                'group' => 'growth', 'type' => Feature::TYPE_LIMIT, 'unit' => 'تقرير',
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false, 'default_value' => 0,
            ],
            [
                'key' => FeatureKey::AUDIENCE_LAB,
                'name' => 'مختبر الجمهور',
                'description' => 'اختبار الرسائل على جمهور اصطناعي قبل إطلاقها.',
                'group' => 'growth', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_GATE,
                'default_enabled' => false,
            ],
            [
                'key' => 'support.priority',
                'name' => 'دعم بأولوية',
                'description' => 'وعد خدمة بشري: ردّ أسرع على طلبات الدعم. لا يمنعه النظام تقنيًا.',
                'group' => 'support', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_DISPLAY,
                'default_enabled' => false,
            ],
            [
                'key' => 'support.onboarding',
                'name' => 'جلسة تهيئة',
                'description' => 'وعد خدمة بشري: جلسة إعداد أولى مع الفريق.',
                'group' => 'support', 'type' => Feature::TYPE_BOOLEAN,
                'enforcement' => Feature::ENFORCEMENT_DISPLAY,
                'default_enabled' => false,
            ],
        ];
    }

    /**
     * توزيع افتراضي قابل للتعديل كليًا من لوحة الآدمن.
     * القيمة null في limit/quota = بلا حد.
     */
    private function assignToPlans(): void
    {
        $matrix = [
            'free' => [
                FeatureKey::PROJECTS_LIMIT => 1,
                FeatureKey::TOOL_RUNS_MONTHLY => 3,
                FeatureKey::COMPETITORS_LIMIT => 3,
            ],
            'individual' => [
                FeatureKey::PROJECTS_LIMIT => 3,
                FeatureKey::TOOL_RUNS_MONTHLY => 20,
                FeatureKey::COMPETITORS_LIMIT => 5,
                FeatureKey::REPORTS_PDF => true,
                FeatureKey::KPI_TRACKING => true,
                FeatureKey::WATCHERS_LIMIT => 2,
                FeatureKey::DIAGNOSIS_FULL => true,
            ],
            'professional' => [
                FeatureKey::PROJECTS_LIMIT => 10,
                FeatureKey::TOOL_RUNS_MONTHLY => 80,
                FeatureKey::COMPETITORS_LIMIT => 10,
                FeatureKey::REPORTS_PDF => true,
                FeatureKey::REPORTS_AGENCY => true,
                FeatureKey::KPI_TRACKING => true,
                FeatureKey::WATCHERS_LIMIT => 10,
                FeatureKey::DIAGNOSIS_FULL => true,
                FeatureKey::GROWTH_PULSE => true,
                FeatureKey::GROWTH_GEO => true,
                'support.priority' => true,
            ],
            'team' => [
                FeatureKey::PROJECTS_LIMIT => 20,
                FeatureKey::TOOL_RUNS_MONTHLY => null,
                FeatureKey::COMPETITORS_LIMIT => null,
                FeatureKey::REPORTS_PDF => true,
                FeatureKey::REPORTS_AGENCY => true,
                FeatureKey::MANUAL_REVIEW => true,
                FeatureKey::KPI_TRACKING => true,
                FeatureKey::WATCHERS_LIMIT => null,
                FeatureKey::DIAGNOSIS_FULL => true,
                FeatureKey::GROWTH_PULSE => true,
                FeatureKey::GROWTH_GEO => true,
                FeatureKey::AUDIENCE_LAB => true,
                'support.priority' => true,
                'support.onboarding' => true,
            ],
        ];

        $features = Feature::pluck('id', 'key');

        foreach ($matrix as $planKey => $selection) {
            $plan = Plan::where('key', $planKey)->first();

            if ($plan === null) {
                continue;
            }

            foreach ($selection as $featureKey => $value) {
                $featureId = $features[$featureKey] ?? null;

                if ($featureId === null) {
                    continue;
                }

                PlanFeature::updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_id' => $featureId],
                    [
                        'enabled' => $value !== false,
                        'value' => is_int($value) ? $value : null,
                    ],
                );
            }

            // حد المشاريع يبقى متسقًا مع العمود القديم حتى لا يتناقض مصدران.
            $projectLimit = $selection[FeatureKey::PROJECTS_LIMIT] ?? null;

            if (is_int($projectLimit)) {
                $plan->forceFill(['project_limit' => $projectLimit])->save();
            }
        }
    }
}
