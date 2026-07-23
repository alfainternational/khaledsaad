<?php

namespace App\Services\Tools;

/**
 * رؤية كاملة للمنافسين: أين يرى صاحب المشروع إعلانات منافسيه على كل منصة.
 *
 * أغلب المنصات الكبرى تتيح «مكتبة إعلانات» عامة يظهر فيها كل إعلان نشط لأي
 * معلِن — بصورته ونصه وتاريخه. هذه الطبقة تربط كل منصة يختارها المستخدم
 * بمصدر رؤية منافسيه عليها، بروابط رسمية أولى الطرف.
 *
 * نلتزم بنفس صدق أرقام السوق: ما له مكتبة عامة نقول أين، وما لا مكتبة له
 * نقول ذلك صراحة ونرشد إلى البديل بدل ادعاء ما لا نملكه.
 */
class AdLibraries
{
    /**
     * قيمة خيار المنصة ← مصدر رؤية المنافسين عليها.
     * منصات جوجل الأربع تشترك في مركز واحد، فتُدمج في مدخل واحد عند العرض.
     */
    private const SOURCES = [
        'meta' => [
            'label' => 'فيسبوك وإنستغرام',
            'source' => 'مكتبة إعلانات Meta',
            'url' => 'https://www.facebook.com/ads/library',
            'what' => 'كل إعلان نشط لأي صفحة، بصورته ونصه وتاريخ إطلاقه.',
            'group' => 'meta',
        ],
        'whatsapp' => [
            'label' => 'واتساب (محادثة مباشرة)',
            'source' => 'مكتبة إعلانات Meta',
            'url' => 'https://www.facebook.com/ads/library',
            'what' => 'إعلانات «راسلنا على واتساب» تظهر ضمن مكتبة Meta نفسها.',
            'group' => 'meta',
        ],
        'google_search' => [
            'label' => 'بحث Google',
            'source' => 'مركز شفافية إعلانات Google',
            'url' => 'https://adstransparency.google.com',
            'what' => 'إعلانات أي معلِن عبر البحث وYouTube والشبكة والتسوق.',
            'group' => 'google',
        ],
        'google_display' => [
            'label' => 'شبكة Google',
            'source' => 'مركز شفافية إعلانات Google',
            'url' => 'https://adstransparency.google.com',
            'what' => 'إعلانات أي معلِن عبر البحث وYouTube والشبكة والتسوق.',
            'group' => 'google',
        ],
        'google_shopping' => [
            'label' => 'Google Shopping',
            'source' => 'مركز شفافية إعلانات Google',
            'url' => 'https://adstransparency.google.com',
            'what' => 'إعلانات أي معلِن عبر البحث وYouTube والشبكة والتسوق.',
            'group' => 'google',
        ],
        'youtube' => [
            'label' => 'YouTube (فيديو)',
            'source' => 'مركز شفافية إعلانات Google',
            'url' => 'https://adstransparency.google.com',
            'what' => 'إعلانات أي معلِن عبر البحث وYouTube والشبكة والتسوق.',
            'group' => 'google',
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'source' => 'مكتبة محتوى TikTok التجاري',
            'url' => 'https://library.tiktok.com',
            'what' => 'الإعلانات النشطة حسب الدولة والمُعلن.',
            'group' => 'tiktok',
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'source' => 'مكتبة إعلانات LinkedIn',
            'url' => 'https://www.linkedin.com/ad-library',
            'what' => 'إعلانات أي شركة على LinkedIn.',
            'group' => 'linkedin',
        ],
        'snapchat' => [
            'label' => 'Snapchat',
            'source' => 'مكتبة Snapchat السياسية',
            'url' => 'https://www.snap.com/en-US/political-ads',
            'what' => 'محدودة: الإعلانات السياسية والتوعوية فقط، لا التجارية.',
            'group' => 'snapchat',
            'limited' => true,
        ],
        'x' => [
            'label' => 'X',
            'source' => 'X',
            'url' => null,
            'what' => 'لا مكتبة عامة شاملة حاليًا؛ الشفافية محدودة.',
            'group' => 'x',
            'limited' => true,
        ],
        'noon' => [
            'label' => 'نون',
            'source' => 'نون',
            'url' => null,
            'what' => 'لا مكتبة عامة؛ ابحث عن منتجك وشاهد نتائج «برعاية» لترى منافسيك.',
            'group' => 'noon',
            'limited' => true,
        ],
        'amazon' => [
            'label' => 'أمازون السعودية',
            'source' => 'أمازون',
            'url' => null,
            'what' => 'لا مكتبة عامة؛ ابحث عن منتجك وشاهد نتائج «برعاية» لترى منافسيك.',
            'group' => 'amazon',
            'limited' => true,
        ],
    ];

    /**
     * رؤية المنافسين على مجموعة من المنصات، مدموجة بلا تكرار للمصدر الواحد.
     *
     * @param  array<int, string>  $platformValues
     * @return array<int, array{source: string, url: ?string, what: string, platforms: string, limited: bool}>
     */
    public function forPlatforms(array $platformValues): array
    {
        $groups = [];

        foreach ($platformValues as $value) {
            $entry = self::SOURCES[$value] ?? null;

            if ($entry === null) {
                continue;
            }

            $key = $entry['group'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'source' => $entry['source'],
                    'url' => $entry['url'],
                    'what' => $entry['what'],
                    'limited' => $entry['limited'] ?? false,
                    'labels' => [],
                ];
            }

            $groups[$key]['labels'][] = $entry['label'];
        }

        return collect($groups)
            ->map(fn (array $group) => [
                'source' => $group['source'],
                'url' => $group['url'],
                'what' => $group['what'],
                'limited' => $group['limited'],
                'platforms' => implode(' و', array_unique($group['labels'])),
            ])
            ->values()
            ->all();
    }

    public function hasSourceFor(string $platformValue): bool
    {
        return isset(self::SOURCES[$platformValue]);
    }
}
