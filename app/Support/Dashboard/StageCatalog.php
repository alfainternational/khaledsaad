<?php

namespace App\Support\Dashboard;

class StageCatalog
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function all(): array
    {
        return [
            1 => [
                'key' => 'discover',
                'label' => 'اعرف وضعك',
                'description' => 'نحدّد فكرتك وهدفك والمشكلة التي تحلّها لعميلك.',
                'core_tools' => ['diagnosis', 'idea-clarity', 'goal-definition', 'problem-definition', 'swot-analysis'],
            ],
            2 => [
                'key' => 'foundation',
                'label' => 'جهّز رسالتك',
                'description' => 'من عميلك؟ ماذا تقول له؟ وبماذا تتميّز عن غيرك؟',
                'core_tools' => ['tagline-builder', 'ideal-customer', 'positioning', 'market-analysis', 'competitor-analysis'],
            ],
            3 => [
                'key' => 'offer',
                'label' => 'جهّز عرضك',
                'description' => 'ماذا تقدّم؟ بكم؟ ولماذا يستحق أن يدفع له عميلك؟',
                'core_tools' => ['offer-builder', 'pricing-strategy', 'value-ladder', 'package-builder', 'promise-builder'],
            ],
            4 => [
                'key' => 'conversion',
                'label' => 'اجلب عملاء',
                'description' => 'كيف توصل لعملائك وتحوّلهم من مهتمّين إلى مشترين؟',
                'core_tools' => ['funnel-builder', 'customer-journey', 'marketing-plan', 'content-plan', 'campaign-builder', 'follow-up-sequence'],
            ],
            5 => [
                'key' => 'scale',
                'label' => 'قِس وكبّر',
                'description' => 'اعرف ما الذي ينجح، وخطّط لتنمو أكثر.',
                'core_tools' => ['kpi-tracker', 'execution-plan', 'performance-review', 'smart-recommendations', 'growth-priorities'],
            ],
        ];
    }

    public static function label(int $stage): string
    {
        return static::all()[$stage]['label'] ?? 'مرحلة غير معروفة';
    }

    public static function description(int $stage): string
    {
        return static::all()[$stage]['description'] ?? 'لا توجد تفاصيل لهذه المرحلة.';
    }
}
