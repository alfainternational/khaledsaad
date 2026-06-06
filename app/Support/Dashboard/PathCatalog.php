<?php

namespace App\Support\Dashboard;

class PathCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'start_your_project' => [
                'label' => 'ابدأ مشروعك',
                'description' => 'مسار للبداية يساعدك توضّح فكرتك وتعرف عميلك، ثم تبني أول عرض جاهز للانطلاق.',
                'personas' => ['idea'],
                'goals' => ['clarify_idea', 'build_offer'],
                'awareness_levels' => ['guided', 'structured'],
                'stage_focus' => [1, 2, 3],
            ],
            'service_growth' => [
                'label' => 'نمو مقدم الخدمة',
                'description' => 'يحوّل خدمتك إلى عرض واضح، مع رسائل متابعة وحملات ومحتوى يجذب العملاء.',
                'personas' => ['freelancer'],
                'goals' => ['build_offer', 'get_first_customers', 'launch_campaigns'],
                'awareness_levels' => ['guided', 'structured', 'expert'],
                'stage_focus' => [2, 3, 4],
            ],
            'growth_system' => [
                'label' => 'نظام نمو لمشروع قائم',
                'description' => 'لمشروع شغّال يريد يعيد ضبط السوق والعرض وخطوات جذب العميل وقياس النتائج.',
                'personas' => ['business'],
                'goals' => ['improve_marketing', 'build_90_day_plan'],
                'awareness_levels' => ['structured', 'expert'],
                'stage_focus' => [2, 3, 4, 5],
            ],
            'growth_operations' => [
                'label' => 'تشغيل فريق النمو',
                'description' => 'يركّز على تجهيز التنفيذ وتوزيع العمل والتقارير وقياس نتائج الفريق.',
                'personas' => ['team'],
                'goals' => ['build_90_day_plan', 'improve_marketing'],
                'awareness_levels' => ['structured', 'expert'],
                'stage_focus' => [3, 4, 5],
            ],
            'agency_delivery' => [
                'label' => 'تشغيل الوكالة',
                'description' => 'مسار لعدة عملاء يوازن بين بناء العروض والحملات والموافقات وقياس النتائج.',
                'personas' => ['agency'],
                'goals' => ['build_offer', 'launch_campaigns', 'build_90_day_plan'],
                'awareness_levels' => ['structured', 'expert'],
                'stage_focus' => [2, 3, 4, 5],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(static::all())
            ->mapWithKeys(fn (array $path, string $key) => [$key => $path['label']])
            ->all();
    }

    public static function exists(?string $path): bool
    {
        return $path !== null && array_key_exists($path, static::all());
    }

    public static function recommend(?string $persona, ?string $goal, ?string $awareness): string
    {
        $paths = collect(static::all())
            ->filter(function (array $path) use ($persona, $goal, $awareness): bool {
                return in_array($persona, $path['personas'], true)
                    && in_array($goal, $path['goals'], true)
                    && in_array($awareness, $path['awareness_levels'], true);
            });

        if ($paths->isNotEmpty()) {
            return (string) $paths->keys()->first();
        }

        return match ($persona) {
            'freelancer' => 'service_growth',
            'business' => 'growth_system',
            'team' => 'growth_operations',
            'agency' => 'agency_delivery',
            default => 'start_your_project',
        };
    }

    public static function label(?string $path): string
    {
        return static::all()[$path]['label'] ?? 'غير محدد';
    }

    public static function description(?string $path): string
    {
        return static::all()[$path]['description'] ?? 'لا يوجد مسار محدد لك بعد.';
    }
}
