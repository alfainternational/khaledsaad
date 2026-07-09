<?php

namespace Database\Seeders;

use App\Domain\AI\Models\AITemplate;
use App\Domain\Billing\Models\Plan;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\Tool\Models\Tool;
use App\Models\User;
use App\Support\AI\StudioTemplateCatalog;
use Illuminate\Database\Seeder;

class PlatformBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaultPlans() as $planData) {
            $entitlements = $planData['entitlements'];
            unset($planData['entitlements']);

            $paypalFromEnv = $this->paypalPlanColumnsFromEnv((string) $planData['code']);

            $plan = Plan::query()->updateOrCreate(
                ['code' => $planData['code']],
                $planData + $paypalFromEnv + ['features_json' => $entitlements]
            );

            Entitlement::query()
                ->where('scope_type', 'plan')
                ->where('scope_id', $plan->getKey())
                ->delete();

            foreach ($entitlements as $key => $value) {
                Entitlement::query()->create([
                    'scope_type' => 'plan',
                    'scope_id' => $plan->getKey(),
                    'key' => $key,
                    'value_type' => is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string'),
                    'value' => ['value' => $value],
                    'source' => 'plan_default',
                ]);
            }
        }

        foreach ($this->defaultFlags() as $flagData) {
            FeatureFlag::query()->updateOrCreate(
                ['key' => $flagData['key']],
                $flagData
            );
        }

        foreach ($this->defaultTools() as $toolData) {
            Tool::query()->updateOrCreate(
                ['code' => $toolData['code']],
                $toolData
            );
        }

        foreach ($this->defaultTemplates() as $templateData) {
            AITemplate::query()->updateOrCreate(
                ['code' => $templateData['code']],
                $templateData
            );
        }

        User::query()->updateOrCreate(
            ['email' => config('platform.admin.email')],
            [
                'name' => config('platform.admin.name'),
                'password' => config('platform.admin.password'),
                'locale' => 'ar',
                'status' => 'active',
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * معرّفات خطط PayPal من .env فقط عند تعبئتها (لا تُصفّر أعمدة DB عند الفراغ).
     *
     * @return array<string, string>
     */
    private function paypalPlanColumnsFromEnv(string $code): array
    {
        if ($code === 'free') {
            return [];
        }

        $prefix = strtoupper($code);
        $out = [];

        $mKey = "PAYPAL_PLAN_{$prefix}_MONTHLY";
        $aKey = "PAYPAL_PLAN_{$prefix}_ANNUAL";
        if (filled(env($mKey))) {
            $out['paypal_plan_id_monthly'] = (string) env($mKey);
        }
        if (filled(env($aKey))) {
            $out['paypal_plan_id_annual'] = (string) env($aKey);
        }

        if (in_array($code, ['starter', 'team'], true)) {
            if (! isset($out['paypal_plan_id_monthly']) && filled(env('PAYPAL_PLAN_PRO_MONTHLY'))) {
                $out['paypal_plan_id_monthly'] = (string) env('PAYPAL_PLAN_PRO_MONTHLY');
            }
            if (! isset($out['paypal_plan_id_annual']) && filled(env('PAYPAL_PLAN_PRO_ANNUAL'))) {
                $out['paypal_plan_id_annual'] = (string) env('PAYPAL_PLAN_PRO_ANNUAL');
            }
        }

        if ($code === 'agency') {
            if (! isset($out['paypal_plan_id_monthly']) && filled(env('PAYPAL_PLAN_ENT_MONTHLY'))) {
                $out['paypal_plan_id_monthly'] = (string) env('PAYPAL_PLAN_ENT_MONTHLY');
            }
            if (! isset($out['paypal_plan_id_annual']) && filled(env('PAYPAL_PLAN_ENT_ANNUAL'))) {
                $out['paypal_plan_id_annual'] = (string) env('PAYPAL_PLAN_ENT_ANNUAL');
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultPlans(): array
    {
        return [
            [
                'code' => 'free',
                'name_ar' => 'Free',
                'name_en' => 'Free',
                'monthly_price' => 0,
                'annual_price' => 0,
                'status' => 'active',
                'entitlements' => [
                    'modules.stage_1' => true,
                    'modules.stage_2' => false,
                    'modules.ai_studio' => false,
                    'integrations.cloud_http' => false,
                    'outputs.can_export' => false,
                    'white_label' => false,
                    'projects.max_per_workspace' => 1,
                    'workspaces.max' => 1,
                ],
            ],
            [
                'code' => 'starter',
                'name_ar' => 'Starter',
                'name_en' => 'Starter',
                'monthly_price' => 49,
                'annual_price' => 470,
                'status' => 'active',
                'entitlements' => [
                    'modules.stage_1' => true,
                    'modules.stage_2' => true,
                    'modules.stage_3' => true,
                    'modules.ai_studio' => false,
                    'integrations.cloud_http' => false,
                    'outputs.can_export' => true,
                    'white_label' => false,
                    'intelligence.market_signals' => true,
                    'modules.seo' => true,
                    'projects.max_per_workspace' => 3,
                    'workspaces.max' => 1,
                ],
            ],
            [
                'code' => 'pro',
                'name_ar' => 'Pro',
                'name_en' => 'Pro',
                'monthly_price' => 149,
                'annual_price' => 1490,
                'status' => 'active',
                'entitlements' => [
                    'modules.stage_1' => true,
                    'modules.stage_2' => true,
                    'modules.stage_3' => true,
                    'modules.stage_4' => true,
                    'modules.stage_5' => true,
                    'modules.ai_studio' => true,
                    'integrations.cloud_http' => true,
                    'outputs.can_export' => true,
                    'white_label' => false,
                    'intelligence.market_signals' => true,
                    'modules.seo' => true,
                    'modules.campaigns' => true,
                    'modules.journeys' => true,
                    'modules.growth' => true,
                    'analytics.advanced' => true,
                    'monitoring' => true,
                    'projects.max_per_workspace' => 10,
                    'workspaces.max' => 2,
                ],
            ],
            [
                'code' => 'team',
                'name_ar' => 'Team',
                'name_en' => 'Team',
                'monthly_price' => 299,
                'annual_price' => 2990,
                'status' => 'active',
                'entitlements' => [
                    'modules.stage_1' => true,
                    'modules.stage_2' => true,
                    'modules.stage_3' => true,
                    'modules.stage_4' => true,
                    'modules.stage_5' => true,
                    'modules.ai_studio' => true,
                    'integrations.cloud_http' => true,
                    'outputs.can_export' => true,
                    'white_label' => false,
                    'intelligence.market_signals' => true,
                    'modules.seo' => true,
                    'modules.campaigns' => true,
                    'modules.journeys' => true,
                    'modules.growth' => true,
                    'analytics.advanced' => true,
                    'monitoring' => true,
                    'intelligence.monitoring' => true,
                    'execution.publish' => true,
                    'modules.crm' => true,
                    'projects.max_per_workspace' => 25,
                    'workspaces.max' => 3,
                ],
            ],
            [
                'code' => 'agency',
                'name_ar' => 'Agency',
                'name_en' => 'Agency',
                'monthly_price' => 599,
                'annual_price' => 5990,
                'status' => 'active',
                'entitlements' => [
                    'modules.stage_1' => true,
                    'modules.stage_2' => true,
                    'modules.stage_3' => true,
                    'modules.stage_4' => true,
                    'modules.stage_5' => true,
                    'modules.ai_studio' => true,
                    'modules.agency_mode' => true,
                    'integrations.cloud_http' => true,
                    'outputs.can_export' => true,
                    'white_label' => true,
                    'intelligence.market_signals' => true,
                    'modules.seo' => true,
                    'modules.campaigns' => true,
                    'modules.journeys' => true,
                    'modules.growth' => true,
                    'analytics.advanced' => true,
                    'monitoring' => true,
                    'intelligence.monitoring' => true,
                    'execution.publish' => true,
                    'modules.crm' => true,
                    'modules.influencer' => true,
                    'modules.pr' => true,
                    'projects.max_per_workspace' => 999,
                    'workspaces.max' => 5,
                ],
            ],
            [
                'code' => 'enterprise',
                'name_ar' => 'Enterprise',
                'name_en' => 'Enterprise',
                'monthly_price' => 0,
                'annual_price' => null,
                'status' => 'inactive',
                'entitlements' => [
                    'modules.stage_1' => true,
                    'modules.stage_2' => true,
                    'modules.stage_3' => true,
                    'modules.stage_4' => true,
                    'modules.stage_5' => true,
                    'modules.ai_studio' => true,
                    'modules.agency_mode' => true,
                    'integrations.cloud_http' => true,
                    'outputs.can_export' => true,
                    'white_label' => true,
                    'intelligence.market_signals' => true,
                    'modules.seo' => true,
                    'modules.campaigns' => true,
                    'modules.journeys' => true,
                    'modules.growth' => true,
                    'analytics.advanced' => true,
                    'monitoring' => true,
                    'intelligence.monitoring' => true,
                    'execution.publish' => true,
                    'modules.crm' => true,
                    'modules.influencer' => true,
                    'modules.pr' => true,
                    'projects.max_per_workspace' => 9999,
                    'workspaces.max' => 999,
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultFlags(): array
    {
        return [
            [
                'key' => 'core.admin_panel',
                'name' => 'لوحة الإدارة',
                'description' => 'تشغيل لوحة الإدارة المخصصة.',
                'module' => 'core.admin',
                'status' => 'on',
                'rollout_percentage' => 100,
                'expires_at' => null,
            ],
            [
                'key' => 'ai_studio.new_templates',
                'name' => 'قوالب الاستوديو الجديدة',
                'description' => 'إتاحة القوالب الجديدة للاستوديو الذكي.',
                'module' => 'modules.ai_studio',
                'status' => 'beta',
                'rollout_percentage' => 25,
                'expires_at' => null,
            ],
            [
                'key' => 'agency.beta_workspace',
                'name' => 'تجربة الوكالة التجريبية',
                'description' => 'تشغيل تجريبي لوضع الوكالة.',
                'module' => 'modules.agency_mode',
                'status' => 'off',
                'rollout_percentage' => 0,
                'expires_at' => null,
            ],
            [
                'key' => 'integrations.cloud_http',
                'name' => 'تكامل HTTP السحابي',
                'description' => 'السماح بطلبات التكامل الخارجي عند ضبط الخدمة والاشتراك.',
                'module' => 'integrations.cloud',
                'status' => 'on',
                'rollout_percentage' => 100,
                'expires_at' => null,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultTools(): array
    {
        return collect([
            ['code' => 'diagnosis', 'name' => 'التشخيص', 'description' => 'تشخيص سريع لوضع المشروع الحالي ونقطة انطلاقه.', 'module' => 'modules.stage_1', 'stage' => 1, 'sort_order' => 10, 'status' => 'published'],
            ['code' => 'idea-clarity', 'name' => 'وضوح الفكرة', 'description' => 'تحديد الفكرة بشكل مفهوم وقابل للتنفيذ.', 'module' => 'modules.stage_1', 'stage' => 1, 'sort_order' => 20, 'status' => 'published'],
            ['code' => 'swot-analysis', 'name' => 'تحليل SWOT', 'description' => 'تحليل نقاط القوة والضعف والفرص والتهديدات.', 'module' => 'modules.stage_1', 'stage' => 1, 'sort_order' => 30, 'status' => 'published'],
            ['code' => 'goal-definition', 'name' => 'تحديد الهدف', 'description' => 'صياغة الهدف التجاري أو التسويقي الأقرب حالياً.', 'module' => 'modules.stage_1', 'stage' => 1, 'sort_order' => 40, 'status' => 'published'],
            ['code' => 'problem-definition', 'name' => 'تحديد المشكلة', 'description' => 'تثبيت المشكلة الأساسية التي يحلها المشروع للعميل.', 'module' => 'modules.stage_1', 'stage' => 1, 'sort_order' => 50, 'status' => 'published'],
            ['code' => 'tagline-builder', 'name' => 'الجملة التعريفية', 'description' => 'بناء رسالة تعريفية مختصرة وواضحة للمشروع.', 'module' => 'modules.stage_2', 'stage' => 2, 'sort_order' => 10, 'status' => 'published'],
            ['code' => 'ideal-customer', 'name' => 'العميل المثالي', 'description' => 'تحديد الجمهور الأساسي وصفاته واحتياجاته.', 'module' => 'modules.stage_2', 'stage' => 2, 'sort_order' => 20, 'status' => 'published'],
            ['code' => 'positioning', 'name' => 'التمركز', 'description' => 'توضيح موقع المشروع في السوق وما يميزه.', 'module' => 'modules.stage_2', 'stage' => 2, 'sort_order' => 30, 'status' => 'published'],
            ['code' => 'market-analysis', 'name' => 'تحليل السوق', 'description' => 'قراءة السوق والفرص والاتجاهات ذات الصلة.', 'module' => 'modules.stage_2', 'stage' => 2, 'sort_order' => 40, 'status' => 'published'],
            ['code' => 'competitor-analysis', 'name' => 'تحليل المنافسين', 'description' => 'مقارنة المنافسين الحاليين واستخراج الفجوات.', 'module' => 'modules.stage_2', 'stage' => 2, 'sort_order' => 50, 'status' => 'published'],
            ['code' => 'offer-builder', 'name' => 'بناء العرض', 'description' => 'تحويل القيمة إلى عرض واضح ومقنع.', 'module' => 'modules.stage_3', 'stage' => 3, 'sort_order' => 10, 'status' => 'published'],
            ['code' => 'pricing-strategy', 'name' => 'التسعير', 'description' => 'بناء منطق تسعير مناسب للسوق والعرض.', 'module' => 'modules.stage_3', 'stage' => 3, 'sort_order' => 20, 'status' => 'published'],
            ['code' => 'value-ladder', 'name' => 'سلم القيمة', 'description' => 'ترتيب مستويات القيمة والعروض عبر مراحل مختلفة.', 'module' => 'modules.stage_3', 'stage' => 3, 'sort_order' => 30, 'status' => 'published'],
            ['code' => 'package-builder', 'name' => 'الحزم', 'description' => 'بناء باقات أو مستويات عرض مختلفة.', 'module' => 'modules.stage_3', 'stage' => 3, 'sort_order' => 40, 'status' => 'published'],
            ['code' => 'promise-builder', 'name' => 'الوعد التسويقي', 'description' => 'صياغة الوعد الرئيسي الذي يقدمه المشروع.', 'module' => 'modules.stage_3', 'stage' => 3, 'sort_order' => 50, 'status' => 'published'],
            ['code' => 'funnel-builder', 'name' => 'القمع التسويقي', 'description' => 'تحديد مراحل الجذب والتحويل والانتقال بينها.', 'module' => 'modules.stage_4', 'stage' => 4, 'sort_order' => 10, 'status' => 'published'],
            ['code' => 'customer-journey', 'name' => 'رحلة العميل', 'description' => 'قراءة رحلة العميل من الوعي حتى الشراء والاحتفاظ.', 'module' => 'modules.stage_4', 'stage' => 4, 'sort_order' => 20, 'status' => 'published'],
            ['code' => 'marketing-plan', 'name' => 'الخطة التسويقية', 'description' => 'بناء خطة تنفيذية للحملات والمحتوى والقنوات.', 'module' => 'modules.stage_4', 'stage' => 4, 'sort_order' => 30, 'status' => 'published'],
            ['code' => 'content-plan', 'name' => 'خطة المحتوى', 'description' => 'تحويل الرسائل إلى محتوى منظم وقابل للتنفيذ.', 'module' => 'modules.stage_4', 'stage' => 4, 'sort_order' => 40, 'status' => 'published'],
            ['code' => 'campaign-builder', 'name' => 'الحملات', 'description' => 'بناء حملة أو أكثر مع الرسائل والقنوات والمتابعة.', 'module' => 'modules.stage_4', 'stage' => 4, 'sort_order' => 50, 'status' => 'published'],
            ['code' => 'follow-up-sequence', 'name' => 'المتابعة', 'description' => 'تصميم التسلسل المناسب للمتابعة والتحويل.', 'module' => 'modules.stage_4', 'stage' => 4, 'sort_order' => 60, 'status' => 'published'],
            ['code' => 'kpi-tracker', 'name' => 'KPIs', 'description' => 'تحديد المؤشرات الأساسية لقراءة الأداء.', 'module' => 'modules.stage_5', 'stage' => 5, 'sort_order' => 10, 'status' => 'published'],
            ['code' => 'execution-plan', 'name' => 'الخطة التنفيذية', 'description' => 'تحويل الرؤية إلى مهام ومواعيد ومسؤوليات.', 'module' => 'modules.stage_5', 'stage' => 5, 'sort_order' => 20, 'status' => 'published'],
            ['code' => 'performance-review', 'name' => 'قراءة الأداء', 'description' => 'قراءة النتائج الحالية وفهم ما نجح وما تعثر.', 'module' => 'modules.stage_5', 'stage' => 5, 'sort_order' => 30, 'status' => 'published'],
            ['code' => 'smart-recommendations', 'name' => 'التوصيات الذكية', 'description' => 'اقتراح الخطوات التحسينية بناء على البيانات الحالية.', 'module' => 'modules.stage_5', 'stage' => 5, 'sort_order' => 40, 'status' => 'published'],
            ['code' => 'growth-priorities', 'name' => 'أولويات التوسع', 'description' => 'ترتيب مسارات النمو الأقرب أثرًا والأوضح مخاطرة.', 'module' => 'modules.stage_5', 'stage' => 5, 'sort_order' => 50, 'status' => 'published'],
        ])->map(function (array $tool): array {
            return array_merge(
                $tool,
                $this->defaultStageMetadata((int) $tool['stage']),
                $this->toolMetadata()[$tool['code']] ?? [],
            );
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStageMetadata(int $stage): array
    {
        return match ($stage) {
            1 => [
                'audience_types_json' => ['idea', 'freelancer', 'business'],
                'goal_tags_json' => ['clarify_idea'],
                'awareness_levels_json' => ['guided', 'structured', 'expert'],
                'output_type' => 'clarity_snapshot',
                'estimated_minutes' => 12,
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
                'next_actions_json' => ['ثبت المشكلة والهدف والجمهور قبل الانتقال للمرحلة التالية.'],
                'depends_on_json' => [],
                'feeds_into_json' => ['tagline-builder', 'ideal-customer', 'offer-builder'],
            ],
            2 => [
                'audience_types_json' => ['idea', 'freelancer', 'business', 'team', 'agency'],
                'goal_tags_json' => ['clarify_idea', 'build_offer', 'improve_marketing'],
                'awareness_levels_json' => ['guided', 'structured', 'expert'],
                'output_type' => 'strategy_foundation',
                'estimated_minutes' => 18,
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
                'next_actions_json' => ['حوّل الوضوح التسويقي إلى عرض أو خطة تنفيذية.'],
                'depends_on_json' => ['diagnosis'],
                'feeds_into_json' => ['offer-builder', 'pricing-strategy', 'marketing-plan'],
            ],
            3 => [
                'audience_types_json' => ['idea', 'freelancer', 'business', 'agency'],
                'goal_tags_json' => ['build_offer', 'get_first_customers'],
                'awareness_levels_json' => ['guided', 'structured', 'expert'],
                'output_type' => 'offer_asset',
                'estimated_minutes' => 20,
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
                'next_actions_json' => ['راجع العرض ثم اربطه بالقمع وخطة التسويق.'],
                'depends_on_json' => ['ideal-customer', 'positioning'],
                'feeds_into_json' => ['funnel-builder', 'campaign-builder', 'promise-builder'],
            ],
            4 => [
                'audience_types_json' => ['freelancer', 'business', 'team', 'agency'],
                'goal_tags_json' => ['get_first_customers', 'launch_campaigns', 'improve_marketing'],
                'awareness_levels_json' => ['structured', 'expert'],
                'output_type' => 'execution_plan',
                'estimated_minutes' => 22,
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
                'next_actions_json' => ['حوّل هذه المخرجات إلى محتوى أو حملة أو متابعة فعلية.'],
                'depends_on_json' => ['offer-builder', 'pricing-strategy'],
                'feeds_into_json' => ['kpi-tracker', 'execution-plan', 'follow-up-sequence'],
            ],
            default => [
                'audience_types_json' => ['business', 'team', 'agency'],
                'goal_tags_json' => ['improve_marketing', 'build_90_day_plan'],
                'awareness_levels_json' => ['guided', 'structured', 'expert'],
                'output_type' => 'performance_system',
                'estimated_minutes' => 18,
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
                'next_actions_json' => ['اتخذ قرار التحسين التالي بناء على الجاهزية والأداء الحالي.'],
                'depends_on_json' => ['marketing-plan', 'campaign-builder'],
                'feeds_into_json' => ['performance-review', 'growth-priorities'],
            ],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function toolMetadata(): array
    {
        return [
            'diagnosis' => [
                'output_type' => 'diagnosis_summary',
                'next_actions_json' => ['انتقل إلى وضوح الفكرة أو تعريف المشكلة بحسب نتيجة التشخيص.'],
            ],
            'ideal-customer' => [
                'output_type' => 'audience_profile',
                'next_actions_json' => ['استخدم هذا الملف في العرض والرسائل والمحتوى.'],
            ],
            'offer-builder' => [
                'output_type' => 'offer_blueprint',
                'feeds_into_json' => ['pricing-strategy', 'funnel-builder', 'promise-builder'],
            ],
            'marketing-plan' => [
                'output_type' => 'marketing_plan',
                'feeds_into_json' => ['content-plan', 'campaign-builder', 'kpi-tracker'],
            ],
            'execution-plan' => [
                'output_type' => 'execution_board',
                'next_actions_json' => ['وزّع المسؤوليات وحدد المواعيد ثم راجع الأداء دورياً.'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultTemplates(): array
    {
        return $this->studioTemplateCatalog()->seededTemplates();
    }

    /**
     * يبقي تعريف القوالب المزروعة مطابقاً لتعريفات التشغيل والتحقق.
     */
    private function studioTemplateCatalog(): StudioTemplateCatalog
    {
        return app(StudioTemplateCatalog::class);
    }
}
