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
                'description' => 'تحتاج توضّح فكرتك ورسالتك وتعرف خطوتك التالية.',
                'workspace_types' => ['personal'],
                'focus' => ['discover', 'foundation', 'offer'],
                'default_awareness' => 'guided',
            ],
            'freelancer' => [
                'label' => 'مقدم خدمة',
                'description' => 'تتعامل مع أكثر من عميل أو عرض وتحتاج تنفيذاً سريعاً ورسائل جاهزة.',
                'workspace_types' => ['personal', 'agency'],
                'focus' => ['offer', 'follow_up', 'content'],
                'default_awareness' => 'structured',
            ],
            'business' => [
                'label' => 'صاحب مشروع قائم',
                'description' => 'تحتاج صورة أوضح عن نتائجك وعرضك وخطوات جذب العميل وخطتك.',
                'workspace_types' => ['personal', 'team'],
                'focus' => ['market', 'funnel', 'kpis'],
                'default_awareness' => 'structured',
            ],
            'team' => [
                'label' => 'فريق أو شركة',
                'description' => 'تحتاج تنظيم الأعضاء والمشاريع والتقارير مع إدارة عمل واضحة.',
                'workspace_types' => ['team'],
                'focus' => ['team', 'execution', 'reports'],
                'default_awareness' => 'expert',
            ],
            'agency' => [
                'label' => 'وكالة',
                'description' => 'تدير عدة عملاء ومشاريع وموافقات في مكان واحد.',
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
        return static::all()[$persona]['description'] ?? 'لم تحدد نوع استخدامك بعد.';
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
