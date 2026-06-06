<?php

namespace App\Support\Dashboard;

class AwarenessCatalog
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return [
            'guided' => [
                'label' => 'بسيط',
                'description' => 'خطوات أقل وكلام أوضح؛ مناسب عندما تريد الإجابة بسرعة من غير تفاصيل زائدة.',
                'tone' => 'شرح قصير وأمثلة قريبة من واقعك.',
            ],
            'structured' => [
                'label' => 'مرتّب',
                'description' => 'يربط بين وضع مشروعك الآن والنتيجة التي تحتاجها في الخطة أو العرض.',
                'tone' => 'ترتيب الأفكار وربطها بخطوة عملية تالية.',
            ],
            'expert' => [
                'label' => 'مفصّل',
                'description' => 'أسئلة أكثر وتفاصيل أعمق؛ مناسب عندما لديك خلفية أو قرار يحتاج تحليلاً أدق.',
                'tone' => 'تركيز على الخيارات والتحليل أكثر من إعادة الشرح من البداية.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(static::all())
            ->mapWithKeys(fn (array $mode, string $key) => [$key => $mode['label']])
            ->all();
    }

    public static function exists(?string $awareness): bool
    {
        return $awareness !== null && array_key_exists($awareness, static::all());
    }

    public static function label(?string $awareness): string
    {
        return static::all()[$awareness]['label'] ?? 'غير محدد';
    }

    public static function description(?string $awareness): string
    {
        return static::all()[$awareness]['description'] ?? 'لم تحدد مستوى التفصيل بعد.';
    }
}
