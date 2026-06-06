<?php

namespace App\Support\Dashboard;

class GoalCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'clarify_idea' => [
                'label' => 'توضيح الفكرة',
                'description' => 'تحدد فكرتك وهدفك والمشكلة التي تحلّها ومن هو عميلك بوضوح.',
                'focus_stages' => [1, 2],
            ],
            'build_offer' => [
                'label' => 'بناء العرض',
                'description' => 'تحوّل ما تقدّمه إلى عرض واضح بسعر ووعد وباقات مناسبة.',
                'focus_stages' => [2, 3],
            ],
            'get_first_customers' => [
                'label' => 'الحصول على أول عملاء',
                'description' => 'تنتقل من الفهم إلى خطوات جذب العميل والمحتوى والمتابعة وإتمام البيع.',
                'focus_stages' => [3, 4],
            ],
            'improve_marketing' => [
                'label' => 'تحسين التسويق',
                'description' => 'تحسّن رسائلك وقنواتك وحملاتك ومحتواك وتربطها بالنتائج.',
                'focus_stages' => [2, 4, 5],
            ],
            'launch_campaigns' => [
                'label' => 'إطلاق حملات',
                'description' => 'تبني حملة عملية لها عميل محدد ورسائل وقنوات ومتابعة واضحة.',
                'focus_stages' => [3, 4],
            ],
            'build_90_day_plan' => [
                'label' => 'بناء خطة 90 يوم',
                'description' => 'تحوّل خطتك إلى أولويات وأرقام نجاح وخطوات تنفيذ يمكنك متابعتها.',
                'focus_stages' => [4, 5],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(static::all())
            ->mapWithKeys(fn (array $goal, string $key) => [$key => $goal['label']])
            ->all();
    }

    public static function exists(?string $goal): bool
    {
        return $goal !== null && array_key_exists($goal, static::all());
    }

    public static function label(?string $goal): string
    {
        return static::all()[$goal]['label'] ?? 'غير محدد';
    }

    public static function description(?string $goal): string
    {
        return static::all()[$goal]['description'] ?? 'لم تحدد هدفك الحالي بعد.';
    }
}
