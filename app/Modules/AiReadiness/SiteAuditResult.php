<?php

namespace App\Modules\AiReadiness;

/**
 * نتيجة تدقيق موقع واحد.
 *
 * كل حقل هنا **مرصود** من صفحة حقيقية لا مأخوذ من وصف صاحب النشاط لموقعه.
 * هذا ما يجعل المحور ٧ قابلًا للبيع: رقم لا يعتمد على من يُقاس.
 */
final class SiteAuditResult
{
    /**
     * @param  array<int, string>  $policyPages  عناوين صفحات السياسات المرصودة
     * @param  array<int, string>  $notes  ملاحظات تشغيلية (تعذّر جلب، تحويل، …)
     */
    public function __construct(
        public readonly string $url,
        public readonly bool $reachable,
        public readonly bool $schemaOrganization,
        public readonly bool $schemaProducts,
        public readonly bool $pricesMachineReadable,
        public readonly array $policyPages,
        public readonly string $arabicPageStructure,
        public readonly bool $llmsTxt,
        public readonly bool $aiBotsAllowed,
        public readonly array $notes = [],

        /*
         * صفحة البداية كما جُلبت، لجامعي المحاور وحدهم.
         *
         * تُمرَّر ولا تُعرض ولا تُسلسَل: جلبها مرة واحدة يخدم المحورين ٧ و٨
         * معًا، وإعادة جلبها لكل جامع نداءٌ شبكي مكرر على موقع العميل.
         */
        public readonly ?string $homepageHtml = null,
    ) {}

    /**
     * الحقائق كما تُكتب في الدماغ، بمفاتيح `AxisRegistry` نفسها.
     *
     * موقع غير قابل للوصول لا يُنتج حقائق: صفر مرصود يختلف عن صفر مفترض،
     * وكتابة الأول مكان الثاني تجعل التقرير يتّهم موقعًا سليمًا (§٤.٣).
     *
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        if (! $this->reachable) {
            return [];
        }

        return [
            'schema_organization' => $this->schemaOrganization,
            'schema_products' => $this->schemaProducts,
            'prices_machine_readable' => $this->pricesMachineReadable,
            'policy_pages' => $this->policyPages,
            'arabic_page_structure' => $this->arabicPageStructure,
            'llms_txt' => $this->llmsTxt,
            'ai_bots_allowed' => $this->aiBotsAllowed,
        ];
    }

    /**
     * بنود البطاقة: كل بند بحالته وما يعنيه وما يُصلحه.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checklist(): array
    {
        return [
            [
                'key' => 'schema_organization',
                'label' => 'بيانات المنظمة المنظَّمة',
                'passed' => $this->schemaOrganization,
                'why' => 'بدونها لا تعرف النماذج من أنت ولا بم تُعرَّف علامتك.',
                'fix' => 'أضف JSON-LD من نوع Organization يحمل الاسم والشعار ووسائل التواصل.',
            ],
            [
                'key' => 'schema_products',
                'label' => 'بيانات المنتجات المنظَّمة',
                'passed' => $this->schemaProducts,
                'why' => 'المنتج غير الموصوف آليًّا لا يظهر في إجابة عن «أفضل ...».',
                'fix' => 'أضف JSON-LD من نوع Product لكل صفحة منتج.',
            ],
            [
                'key' => 'prices_machine_readable',
                'label' => 'أسعار مقروءة آليًّا',
                'passed' => $this->pricesMachineReadable,
                'why' => 'السعر في صورة أو نص حر لا يدخل المقارنات.',
                'fix' => 'اكتب السعر داخل Offer مع priceCurrency.',
            ],
            [
                'key' => 'policy_pages',
                'label' => 'صفحات السياسات',
                'passed' => count($this->policyPages) >= 3,
                'why' => 'الشحن والاستبدال والخصوصية من أكثر ما يُسأل عنه قبل الشراء.',
                'fix' => 'انشر صفحة مستقلة لكل سياسة بنص واضح لا صورة.',
                'detail' => $this->policyPages,
            ],
            [
                'key' => 'arabic_page_structure',
                'label' => 'بنية الصفحات العربية',
                'passed' => $this->arabicPageStructure === 'good',
                'why' => 'صفحة بلا عناوين متدرّجة ولا lang صحيح تُقرأ آليًّا بصعوبة.',
                'fix' => 'اضبط lang="ar" وdir="rtl" ورتّب العناوين h1 ثم h2.',
                'detail' => $this->arabicPageStructure,
            ],
            [
                'key' => 'llms_txt',
                'label' => 'ملف llms.txt',
                'passed' => $this->llmsTxt,
                'why' => 'يوجّه النماذج إلى ما تريد أن تُقرأ عنك.',
                'fix' => 'انشر llms.txt في جذر الموقع.',
            ],
            [
                'key' => 'ai_bots_allowed',
                'label' => 'بوتات الذكاء غير محجوبة',
                'passed' => $this->aiBotsAllowed,
                'why' => 'حجبها في robots.txt يخرجك من الإجابات كلها مهما فعلت.',
                'fix' => 'راجع robots.txt وأزل منع GPTBot وPerplexityBot وما شابههما.',
            ],
        ];
    }
}
