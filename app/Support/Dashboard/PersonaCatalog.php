<?php

namespace App\Support\Dashboard;

class PersonaCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'idea' => [
                'label' => 'صاحب فكرة',
                'description' => 'تحتاج إلى وضوح في الفكرة، الرسالة، والخطوة التالية.',
                'workspace_types' => ['personal'],
                'focus' => ['discover', 'foundation', 'offer'],
                'default_awareness' => 'guided',
            ],
            'freelancer' => [
                'label' => 'مقدم خدمة',
                'description' => 'تدير أكثر من عميل أو عرض وتحتاج إلى تنفيذ سريع ورسائل جاهزة.',
                'workspace_types' => ['personal', 'agency'],
                'focus' => ['offer', 'follow_up', 'content'],
                'default_awareness' => 'structured',
            ],
            'business' => [
                'label' => 'صاحب مشروع قائم',
                'description' => 'تحتاج إلى رؤية أوضح للأداء والعرض والقمع والخطة.',
                'workspace_types' => ['personal', 'team'],
                'focus' => ['market', 'funnel', 'kpis'],
                'default_awareness' => 'structured',
            ],
            'team' => [
                'label' => 'فريق أو شركة',
                'description' => 'تحتاج إلى تنظيم الأعضاء والمشاريع والتقارير مع قيادة تشغيلية واضحة.',
                'workspace_types' => ['team'],
                'focus' => ['team', 'execution', 'reports'],
                'default_awareness' => 'expert',
            ],
            'agency' => [
                'label' => 'وكالة',
                'description' => 'تدير عدة عملاء ومشاريع واعتمادات ضمن مساحة واحدة.',
                'workspace_types' => ['agency'],
                'focus' => ['clients', 'approvals', 'portfolio'],
                'default_awareness' => 'expert',
            ],
        ];
    }

    public static function exists(?string $persona): bool
    {
        return $persona !== null && array_key_exists($persona, static::all());
    }

    public static function label(?string $persona): string
    {
        return static::all()[$persona]['label'] ?? 'غير محدد';
    }

    public static function description(?string $persona): string
    {
        return static::all()[$persona]['description'] ?? 'لم يتم تحديد نوع الاستخدام بعد.';
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(static::all())
            ->mapWithKeys(fn (array $persona, string $key) => [$key => $persona['label']])
            ->all();
    }

    public static function inferFromWorkspaceType(?string $workspaceType): string
    {
        return match ($workspaceType) {
            'team' => 'team',
            'agency' => 'agency',
            default => 'idea',
        };
    }

    public static function defaultAwareness(?string $persona): string
    {
        return static::all()[$persona]['default_awareness'] ?? 'guided';
    }
}
