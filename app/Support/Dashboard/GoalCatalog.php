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
                'description' => 'تثبيت الفكرة والهدف والمشكلة والجمهور بشكل واضح.',
                'focus_stages' => [1, 2],
            ],
            'build_offer' => [
                'label' => 'بناء العرض',
                'description' => 'تحويل القيمة إلى عرض واضح مع تسعير ووعد وحزم مناسبة.',
                'focus_stages' => [2, 3],
            ],
            'get_first_customers' => [
                'label' => 'الحصول على أول عملاء',
                'description' => 'الانتقال من الوضوح إلى القمع والمحتوى والمتابعة والتحويل.',
                'focus_stages' => [3, 4],
            ],
            'improve_marketing' => [
                'label' => 'تحسين التسويق',
                'description' => 'تحسين الرسائل والقنوات والحملات والمحتوى وربطها بالأداء.',
                'focus_stages' => [2, 4, 5],
            ],
            'launch_campaigns' => [
                'label' => 'إطلاق حملات',
                'description' => 'بناء حملة عملية بجمهور ورسائل وقنوات ومتابعة واضحة.',
                'focus_stages' => [3, 4],
            ],
            'build_90_day_plan' => [
                'label' => 'بناء خطة 90 يوم',
                'description' => 'تحويل الرؤية إلى أولويات ومؤشرات وخطة تنفيذية قابلة للمتابعة.',
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
        return static::all()[$goal]['description'] ?? 'لم يتم تحديد الهدف الحالي بعد.';
    }
}
