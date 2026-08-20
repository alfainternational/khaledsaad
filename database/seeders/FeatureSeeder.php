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
    /**
     * فهرس الميزات. التسميات من `config/catalogue.php` لا مكتوبة هنا:
     * الملف نفسه يُمسح للترجمة (`locales.scan.config.files`)، فكتابتها هنا
     * ثانيةً تصنع مصدر حقيقة ثانيًا يفترقان عند أول تعديل (§١٥).
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        $shape = [
            [FeatureKey::PROJECTS_LIMIT, 'core', Feature::TYPE_LIMIT, true, 1],
            [FeatureKey::TOOL_RUNS_MONTHLY, 'core', Feature::TYPE_QUOTA, true, null],
            [FeatureKey::COMPETITORS_LIMIT, 'core', Feature::TYPE_LIMIT, true, 3],
            [FeatureKey::KPI_TRACKING, 'core', Feature::TYPE_BOOLEAN, false, null],
            [FeatureKey::DIAGNOSIS_FULL, 'core', Feature::TYPE_BOOLEAN, false, null],
            [FeatureKey::LEARNING_MARKETING, 'core', Feature::TYPE_BOOLEAN, true, null],
            [FeatureKey::QUERY_BUDGET_MONTHLY, 'growth', Feature::TYPE_QUOTA, true, 150],
            [FeatureKey::REPORTS_PDF, 'reports', Feature::TYPE_BOOLEAN, false, null],
            [FeatureKey::REPORTS_AGENCY, 'reports', Feature::TYPE_BOOLEAN, false, null],
            [FeatureKey::MANUAL_REVIEW, 'reports', Feature::TYPE_BOOLEAN, false, null],
            [FeatureKey::GROWTH_PULSE, 'growth', Feature::TYPE_BOOLEAN, false, null],
            [FeatureKey::GROWTH_GEO, 'growth', Feature::TYPE_BOOLEAN, false, null],
            [FeatureKey::WATCHERS_LIMIT, 'growth', Feature::TYPE_LIMIT, false, 0],
            [FeatureKey::AUDIENCE_LAB, 'growth', Feature::TYPE_BOOLEAN, false, null],
            ['support.priority', 'support', Feature::TYPE_BOOLEAN, false, null],
            ['support.onboarding', 'support', Feature::TYPE_BOOLEAN, false, null],
        ];

        $labels = (array) config('catalogue.features', []);

        return array_map(static function (array $row) use ($labels): array {
            [$key, $group, $type, $enabled, $value] = $row;
            $label = $labels[$key] ?? [];

            return [
                'key' => $key,
                'name' => $label['name'] ?? $key,
                'description' => $label['description'] ?? null,
                'group' => $group,
                'type' => $type,
                'unit' => $label['unit'] ?? null,
                // ما ليس في FeatureKey::all() وعدُ خدمة بشري لا بوابة تقنية.
                'enforcement' => in_array($key, FeatureKey::all(), true)
                    ? Feature::ENFORCEMENT_GATE
                    : Feature::ENFORCEMENT_DISPLAY,
                'default_enabled' => $enabled,
                'default_value' => $value,
            ];
        }, $shape);
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
                FeatureKey::LEARNING_MARKETING => true,
                FeatureKey::QUERY_BUDGET_MONTHLY => 150,
            ],
            'individual' => [
                FeatureKey::PROJECTS_LIMIT => 3,
                FeatureKey::TOOL_RUNS_MONTHLY => 20,
                FeatureKey::COMPETITORS_LIMIT => 5,
                FeatureKey::REPORTS_PDF => true,
                FeatureKey::KPI_TRACKING => true,
                FeatureKey::WATCHERS_LIMIT => 2,
                FeatureKey::DIAGNOSIS_FULL => true,
                FeatureKey::LEARNING_MARKETING => true,
                FeatureKey::QUERY_BUDGET_MONTHLY => 600,
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
                FeatureKey::LEARNING_MARKETING => true,
                'support.priority' => true,
                FeatureKey::QUERY_BUDGET_MONTHLY => 2000,
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
                FeatureKey::LEARNING_MARKETING => true,
                'support.priority' => true,
                'support.onboarding' => true,
                FeatureKey::QUERY_BUDGET_MONTHLY => 6000,
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
