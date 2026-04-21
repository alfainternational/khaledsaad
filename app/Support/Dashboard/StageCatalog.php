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
                'label' => 'اكتشف مشروعك',
                'description' => 'تشخيص الفكرة والهدف والمشكلة الأساسية.',
                'core_tools' => ['diagnosis', 'idea-clarity', 'goal-definition', 'problem-definition', 'swot-analysis'],
            ],
            2 => [
                'key' => 'foundation',
                'label' => 'ابنِ أساسك التسويقي',
                'description' => 'الجمهور والتموضع والرسالة والسوق.',
                'core_tools' => ['tagline-builder', 'ideal-customer', 'positioning', 'market-analysis', 'competitor-analysis'],
            ],
            3 => [
                'key' => 'offer',
                'label' => 'ابنِ عرضك',
                'description' => 'العرض والتسعير وسلّم القيمة والوعد.',
                'core_tools' => ['offer-builder', 'pricing-strategy', 'value-ladder', 'package-builder', 'promise-builder'],
            ],
            4 => [
                'key' => 'conversion',
                'label' => 'اجذب وحوّل',
                'description' => 'القمع، المحتوى، الحملات، والمتابعة.',
                'core_tools' => ['funnel-builder', 'customer-journey', 'marketing-plan', 'content-plan', 'campaign-builder', 'follow-up-sequence'],
            ],
            5 => [
                'key' => 'scale',
                'label' => 'قِس ووسّع',
                'description' => 'KPIs، الخطة التنفيذية، والتوصيات الذكية.',
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
