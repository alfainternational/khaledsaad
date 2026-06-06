<?php

namespace App\Support\Dashboard;

class ReadinessCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'project_clarity' => [
                'label' => 'وضوح المشروع',
                'tools' => ['diagnosis', 'idea-clarity', 'goal-definition', 'problem-definition'],
            ],
            'audience_clarity' => [
                'label' => 'وضوح العميل والسوق',
                'tools' => ['ideal-customer', 'market-analysis', 'competitor-analysis', 'positioning'],
            ],
            'offer_readiness' => [
                'label' => 'اكتمال العرض',
                'tools' => ['offer-builder', 'pricing-strategy', 'value-ladder', 'package-builder', 'promise-builder'],
            ],
            'marketing_readiness' => [
                'label' => 'اكتمال التسويق',
                'tools' => ['funnel-builder', 'customer-journey', 'marketing-plan', 'content-plan', 'campaign-builder'],
            ],
            'sales_readiness' => [
                'label' => 'اكتمال البيع والمتابعة',
                'tools' => ['offer-builder', 'pricing-strategy', 'funnel-builder', 'follow-up-sequence'],
            ],
            'measurement_readiness' => [
                'label' => 'اكتمال قياس النتائج والتوسّع',
                'tools' => ['kpi-tracker', 'execution-plan', 'performance-review', 'smart-recommendations', 'growth-priorities'],
            ],
        ];
    }
}
