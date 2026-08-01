<?php

namespace App\Modules\AiReadiness;

use App\Modules\Shared\Sectors\Sector;

/**
 * القصاصة الجاهزة للصق مقابل كل بند في بطاقة الجاهزية.
 *
 * «أضف JSON-LD من نوع Organization» تعليمة صحيحة وعديمة الأثر لمن لا يعرف
 * ما هو JSON-LD. البند الذي يُقاس آليًّا ويُصلَح بنصّ ثابت لا عذر لتركه
 * وصفًا: القصاصة هنا حتمية بالكامل — لا نموذج لغوي ولا تكلفة — لأن مخرج
 * `Organization` معياريٌّ لا يحتمل اجتهادًا.
 *
 * الفراغات بين قوسين مربعين ظاهرة: ما لا نعرفه يُطلب صراحةً ولا يُخترع.
 * إلصاق قصاصة فيها اسم مؤلَّف أسوأ من غيابها لأنها تُنشر كما هي.
 */
class RepairSnippets
{
    /**
     * @return array{language: string, code: string, where: string}|null
     */
    public function for(string $key, ?string $sector = null): ?array
    {
        return match ($key) {
            'schema_organization' => [
                'language' => 'html',
                'where' => 'داخل <head> في كل صفحات الموقع، أو في الرئيسية على الأقل.',
                'code' => $this->json([
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => '[اسم نشاطك كما يكتبه الناس]',
                    'url' => 'https://[نطاقك]',
                    'logo' => 'https://[نطاقك]/logo.png',
                    'telephone' => '[رقم التواصل بصيغة دولية مثل 966500000000+]',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => '[المدينة]',
                        'addressCountry' => 'SA',
                    ],
                    'sameAs' => ['https://[رابط حسابك الأول]', 'https://[رابط حسابك الثاني]'],
                ]),
            ],

            'schema_products' => $this->offerSnippet($sector),

            'prices_machine_readable' => [
                'language' => 'html',
                'where' => 'داخل صفحة المنتج أو البرنامج نفسها، مع بيانات العرض.',
                'code' => $this->json([
                    '@context' => 'https://schema.org',
                    '@type' => 'Offer',
                    'price' => '[الرقم بلا فاصلة ولا رمز عملة، مثل 1500]',
                    'priceCurrency' => 'SAR',
                    'availability' => 'https://schema.org/InStock',
                    'url' => 'https://[نطاقك]/[مسار الصفحة]',
                ]),
            ],

            'llms_txt' => [
                'language' => 'text',
                'where' => 'ملف نصّي باسم llms.txt في جذر الموقع: https://[نطاقك]/llms.txt',
                'code' => implode("\n", [
                    '# [اسم نشاطك]',
                    '',
                    '> [سطر واحد: ماذا تقدّم ولمن، بكلمات يستعملها عملاؤك]',
                    '',
                    '## ما نقدّمه',
                    '- [اسم الخدمة أو المنتج الأول]: https://[نطاقك]/[المسار]',
                    '- [اسم الخدمة أو المنتج الثاني]: https://[نطاقك]/[المسار]',
                    '',
                    '## أسئلة يسألها عملاؤنا',
                    '- [السؤال كما يقوله هو]: https://[نطاقك]/[صفحة الجواب]',
                    '',
                    '## معلومات التواصل',
                    '- الهاتف: [رقمك]',
                    '- المدينة: [مدينتك]',
                ]),
            ],

            'ai_bots_allowed' => [
                'language' => 'text',
                'where' => 'ملف robots.txt في جذر الموقع. احذف أي سطر Disallow يخص هذه البوتات، ثم أضف:',
                'code' => implode("\n", [
                    'User-agent: GPTBot',
                    'Allow: /',
                    '',
                    'User-agent: PerplexityBot',
                    'Allow: /',
                    '',
                    'User-agent: ClaudeBot',
                    'Allow: /',
                    '',
                    'User-agent: Google-Extended',
                    'Allow: /',
                ]),
            ],

            'arabic_page_structure' => [
                'language' => 'html',
                'where' => 'في وسم <html> أعلى كل صفحة، وفي ترتيب عناوين المحتوى.',
                'code' => implode("\n", [
                    '<html lang="ar" dir="rtl">',
                    '  <head>',
                    '    <meta charset="utf-8">',
                    '    <title>[عنوان الصفحة كما يبحث عنه الناس]</title>',
                    '  </head>',
                    '  <body>',
                    '    <h1>[عنوان واحد فقط في الصفحة يصف موضوعها]</h1>',
                    '    <h2>[عنوان فرعي أول]</h2>',
                    '    <h2>[عنوان فرعي ثانٍ]</h2>',
                    '  </body>',
                    '</html>',
                ]),
            ],

            // السياسات نصّ تحريري لا قصاصة كود: قالبها هيكل صفحة لا وسم.
            'policy_pages' => [
                'language' => 'text',
                'where' => 'صفحة مستقلة لكل سياسة، بنصّ قابل للتحديد لا صورة.',
                'code' => implode("\n", [
                    'عنوان الصفحة: سياسة [الشحن / الاستبدال والاسترجاع / الخصوصية]',
                    '',
                    'آخر تحديث: [التاريخ]',
                    '',
                    '١) ما تشمله هذه السياسة: [جملة واحدة]',
                    '٢) المدة: [كم يومًا بالضبط]',
                    '٣) الشروط: [عدّد ما يجب أن يتحقق، بندًا بندًا]',
                    '٤) ما لا تشمله: [الاستثناءات صراحةً — الغموض هنا يخسّرك ثقة]',
                    '٥) كيف تطلبها: [الخطوة الأولى بالضبط: رقم، بريد، أو نموذج]',
                ]),
            ],

            default => null,
        };
    }

    /**
     * @return array{language: string, code: string, where: string}
     */
    private function offerSnippet(?string $sector): array
    {
        [$type, $extra] = match ($sector) {
            Sector::EDUCATION => ['Course', [
                'provider' => ['@type' => 'Organization', 'name' => '[اسم المدرسة أو المعهد]'],
                'educationalLevel' => '[المرحلة: ابتدائي / متوسط / دورة مهنية]',
            ]],
            Sector::REAL_ESTATE => ['RealEstateListing', [
                'numberOfRooms' => '[عدد الغرف]',
                'floorSize' => ['@type' => 'QuantitativeValue', 'value' => '[المساحة]', 'unitCode' => 'MTK'],
            ]],
            default => ['Product', [
                'brand' => ['@type' => 'Brand', 'name' => '[اسم علامتك]'],
                'sku' => '[رمز المنتج عندك]',
            ]],
        };

        return [
            'language' => 'html',
            'where' => 'داخل <head> في صفحة كل '.match ($sector) {
                Sector::EDUCATION => 'برنامج أو مرحلة دراسية',
                Sector::REAL_ESTATE => 'وحدة أو إعلان',
                default => 'منتج',
            }.' على حدة، لا في الرئيسية فقط.',
            'code' => $this->json([
                '@context' => 'https://schema.org',
                '@type' => $type,
                'name' => '[الاسم كما يظهر للزائر]',
                'description' => '[سطران يصفان ما يحصل عليه]',
                'url' => 'https://[نطاقك]/[مسار الصفحة]',
                ...$extra,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '[الرقم بلا رمز عملة]',
                    'priceCurrency' => 'SAR',
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): string
    {
        return '<script type="application/ld+json">'."\n"
            .json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n"
            .'</script>';
    }
}
