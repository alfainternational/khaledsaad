<?php

namespace App\Modules\AiReadiness;

use App\Modules\Shared\Sectors\Sector;

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

        /*
         * القطاع الذي فُحص به الموقع: يقرّر تسمية بند «العرض المنظَّم»
         * ونص إصلاحه، ولا يغيّر مفاتيح الحقائق (§٤ من مواصفة التخصص).
         */
        public readonly string $sector = 'general',
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
                ...$this->offerItem(),
                'passed' => $this->schemaProducts,
            ],
            [
                'key' => 'prices_machine_readable',
                'label' => $this->sector === Sector::EDUCATION ? 'رسوم مقروءة آليًّا' : 'أسعار مقروءة آليًّا',
                'passed' => $this->pricesMachineReadable,
                'why' => $this->sector === Sector::EDUCATION
                    ? 'الرسوم في صورة أو ملف PDF لا تدخل مقارنات «كم تكلف مدارس …».'
                    : 'السعر في صورة أو نص حر لا يدخل المقارنات.',
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

    /**
     * بند «العرض المنظَّم» بلسان القطاع: الفحص واحد والمفتاح واحد، لكن مدرسةً
     * تُنصح بـProduct توصيةٌ خاطئة تُفقد البطاقة مصداقيتها أمام صاحبها.
     *
     * @return array{label: string, why: string, fix: string}
     */
    private function offerItem(): array
    {
        return match ($this->sector) {
            Sector::EDUCATION => [
                'label' => 'بيانات البرامج الدراسية المنظَّمة',
                'why' => 'البرنامج غير الموصوف آليًّا لا يظهر في إجابة عن «أفضل مدرسة أو معهد …».',
                'fix' => 'أضف JSON-LD من نوع Course لكل برنامج أو مرحلة دراسية.',
            ],
            Sector::REAL_ESTATE => [
                'label' => 'بيانات العقارات المنظَّمة',
                'why' => 'الوحدة غير الموصوفة آليًّا لا تظهر في إجابة عن «شقق أو أراضٍ في …».',
                'fix' => 'أضف JSON-LD من نوع RealEstateListing لكل وحدة أو إعلان.',
            ],
            default => [
                'label' => 'بيانات المنتجات المنظَّمة',
                'why' => 'المنتج غير الموصوف آليًّا لا يظهر في إجابة عن «أفضل ...».',
                'fix' => 'أضف JSON-LD من نوع Product لكل صفحة منتج.',
            ],
        };
    }
}
