<?php

namespace App\Support\Dashboard;

/**
 * Locale / dialect for marketing content (social, landing pages, ads).
 * Distinct from UI user locale — this drives copy language and register.
 */
final class ContentLocaleCatalog
{
    /**
     * @return array<string, array{label: string, prompt: string}>
     */
    public static function all(): array
    {
        return [
            'ar_modern_fusha' => [
                'label' => 'عربية فصحى معاصرة',
                'prompt' => 'اكتب بالعربية الفصحى المعاصرة الواضحة، مناسبة للمواقع والعروض الرسمية ومعظم الدول العربية.',
            ],
            'ar_gulf' => [
                'label' => 'لهجة خليجية (مبسطة للمحتوى)',
                'prompt' => 'استخدم لهجة خليجية مفهومة في الخليج في النصوص التسويقية مع الإبقاء على احترافية؛ تجنب المبالغة في العامية إن لم يطلب المستخدم ذلك.',
            ],
            'ar_egypt' => [
                'label' => 'لهجة مصرية (مبسطة للمحتوى)',
                'prompt' => 'استخدم لهجة مصرية مفهومة واسعة الانتشار في النصوص التسويقية عند الحاجة للقرب والبساطة.',
            ],
            'ar_levant' => [
                'label' => 'لهجة شامية (مبسطة للمحتوى)',
                'prompt' => 'استخدم لهجة شامية مفهومة في بلاد الشام في النصوص التسويقية مع الحفاظ على وضوح الرسالة.',
            ],
            'ar_magreb' => [
                'label' => 'لهجة مغاربية (مبسطة للمحتوى)',
                'prompt' => 'راعِ خصوصية المغرب العربي في المفردات عند الحاجة؛ حافظ على فهم قارئ من بقية المنطقة العربية.',
            ],
            'en' => [
                'label' => 'إنجليزي (محتوى)',
                'prompt' => 'اكتب المحتوى التسويقي بالإنجليزية وفق السوق المحدد في حقل الدولة.',
            ],
            'ar_en_mixed' => [
                'label' => 'عربي وإنجليزي مختلط (عناوين/مصطلحات)',
                'prompt' => 'يمكن الجمع بين العربية والإنجليزية في العناوين والمصطلحات التقنية حيث يناسب الجمهور، مع جسم عربي واضح ما لم يُطلب غير ذلك.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::all())
            ->map(fn (array $row): string => $row['label'])
            ->all();
    }

    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return 'غير محدد';
        }

        return self::all()[$key]['label'] ?? $key;
    }

    public static function promptInstruction(?string $key): string
    {
        if ($key === null || $key === '') {
            return 'لم يُحدد أسلوب لهجة؛ استخدم عربية فصحى معاصرة واضحة كافتراضي.';
        }

        return self::all()[$key]['prompt'] ?? 'التزم بلغة عربية واضحة مناسبة للتسويق.';
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && $key !== '' && isset(self::all()[$key]);
    }
}
