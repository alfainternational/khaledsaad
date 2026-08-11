<?php

namespace App\Modules\AiReadiness;

use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\Shared\Sectors\Sector;

/**
 * التدقيق التقني للمحور ٧: هل موقعك مقروء آليًّا أصلًا؟
 *
 * يفحص الصفحة الرئيسية و`robots.txt` و`llms.txt` ويستخرج حقائق مرصودة. لا
 * يسأل صاحب النشاط عن شيء ولا يقرأ وصفه لموقعه — وهذا بالضبط ما يجعل نتيجته
 * `measured` وقابلة للبيع (§٥).
 *
 * التحليل نصّي بتعبيرات نمطية لا DOM كامل: نبحث عن وجود بنى محدّدة لا عن
 * تحليل صفحة كاملة، والقارئ النصّي أخف ولا ينهار أمام HTML غير سليم — وهو
 * الغالب على المواقع التي نُدقّقها أصلًا.
 */
class SiteAudit
{
    /** بوتات الذكاء الاصطناعي التي يهمّنا ألّا تكون محجوبة. */
    private const AI_BOTS = ['GPTBot', 'PerplexityBot', 'ClaudeBot', 'Google-Extended', 'CCBot'];

    /** كلمات صفحات السياسات بلسان المتاجر العربية. */
    private const POLICY_TERMS = [
        'الخصوصية' => 'سياسة الخصوصية',
        'الاستبدال' => 'الاستبدال والاسترجاع',
        'الاسترجاع' => 'الاستبدال والاسترجاع',
        'الشحن' => 'الشحن والتوصيل',
        'التوصيل' => 'الشحن والتوصيل',
        'الشروط' => 'الشروط والأحكام',
        'الأحكام' => 'الشروط والأحكام',
    ];

    /**
     * سياسات يسأل عنها مشتري القطاع قبل غيرها (مواصفة التخصص القطاعي).
     *
     * تُضاف إلى القاموس العام لا بدلًا منه: مدرسة عندها «الشروط والأحكام»
     * تُحتسب لها، لكن «القبول والتسجيل» هو ما يسأل عنه وليّ الأمر فعلًا.
     */
    private const SECTOR_POLICY_TERMS = [
        Sector::EDUCATION => [
            'القبول' => 'القبول والتسجيل',
            'التسجيل' => 'القبول والتسجيل',
            'الرسوم' => 'الرسوم والاسترداد',
        ],
        Sector::REAL_ESTATE => [
            'الوساطة' => 'اتفاقية الوساطة',
            'العمولة' => 'اتفاقية الوساطة',
            'فال' => 'ترخيص فال',
        ],
    ];

    /**
     * أنواع «عرض القطاع» المنظَّم: ما يعادل Product عند كل قطاع.
     *
     * مفتاح الحقيقة يبقى `schema_products` (§٤ من المواصفة) — يتسع معناه إلى
     * «بيانات العرض المنظَّمة» ولا يتغيّر اسمه حتى لا ينقطع تاريخ الدماغ.
     */
    private const OFFER_SCHEMA_TYPES = [
        Sector::EDUCATION => ['Course', 'CourseInstance', 'EducationalOccupationalProgram'],
        Sector::REAL_ESTATE => ['RealEstateListing', 'Residence', 'Apartment', 'House', 'SingleFamilyResidence'],
        'general' => ['Product', 'ItemList'],
    ];

    /** أنواع تعريف المنظمة المقبولة زيادةً على العامة عند كل قطاع. */
    private const ORGANIZATION_SCHEMA_TYPES = [
        Sector::EDUCATION => ['EducationalOrganization', 'School', 'CollegeOrUniversity', 'Preschool'],
        Sector::REAL_ESTATE => ['RealEstateAgent'],
    ];

    public function __construct(private readonly PageFetcher $fetcher) {}

    public function audit(string $url, string $sector = 'general'): SiteAuditResult
    {
        $sector = Sector::declaredOrGeneral($sector);
        $base = rtrim($url, '/');
        $html = $this->fetcher->get($base);

        if ($html === null) {
            return new SiteAuditResult(
                url: $base,
                reachable: false,
                schemaOrganization: false,
                schemaProducts: false,
                pricesMachineReadable: false,
                policyPages: [],
                arabicPageStructure: 'poor',
                llmsTxt: false,
                aiBotsAllowed: false,
                notes: [__('تعذّر الوصول إلى الموقع، فلم يُفحص. هذه ليست نتيجة فحص سلبية.')],
                sector: $sector,
            );
        }

        $robots = $this->fetcher->get($base.'/robots.txt');

        return new SiteAuditResult(
            url: $base,
            reachable: true,
            schemaOrganization: $this->hasSchemaType($html, [
                'Organization', 'LocalBusiness', 'Store',
                ...self::ORGANIZATION_SCHEMA_TYPES[$sector] ?? [],
            ]),
            schemaProducts: $this->hasSchemaType($html, self::OFFER_SCHEMA_TYPES[$sector] ?? self::OFFER_SCHEMA_TYPES['general']),
            pricesMachineReadable: $this->hasMachineReadablePrice($html),
            policyPages: $this->policyPages($html, $sector),
            arabicPageStructure: $this->arabicStructure($html),
            llmsTxt: $this->fetcher->get($base.'/llms.txt') !== null,
            aiBotsAllowed: $this->botsAllowed($robots),
            notes: $robots === null ? [__('لا يوجد robots.txt — البوتات غير محجوبة ضمنًا.')] : [],
            homepageHtml: $html,
            sector: $sector,
        );
    }

    /**
     * @param  array<int, string>  $types
     */
    private function hasSchemaType(string $html, array $types): bool
    {
        foreach ($types as $type) {
            // JSON-LD أو Microdata: كلاهما مقروء آليًّا، فلا نحابي صيغة.
            if (preg_match('/"@type"\s*:\s*"'.preg_quote($type, '/').'"/i', $html)
                || preg_match('/itemtype\s*=\s*"https?:\/\/schema\.org\/'.preg_quote($type, '/').'"/i', $html)) {
                return true;
            }
        }

        return false;
    }

    /**
     * السعر مقروء آليًّا حين يقترن برمز عملة داخل بنية منظَّمة.
     *
     * الرقم وحده لا يكفي: «١٩٩» قد تكون سعرًا أو عدد قطع أو رقم موديل.
     */
    private function hasMachineReadablePrice(string $html): bool
    {
        return (bool) preg_match('/"price"\s*:\s*"?[\d.,]+/i', $html)
            && (bool) preg_match('/"priceCurrency"\s*:\s*"[A-Z]{3}"/i', $html);
    }

    /**
     * @return array<int, string>
     */
    private function policyPages(string $html, string $sector = 'general'): array
    {
        $found = [];
        $terms = [...self::POLICY_TERMS, ...self::SECTOR_POLICY_TERMS[$sector] ?? []];

        foreach ($terms as $term => $label) {
            // الرابط لا مجرد ذكر الكلمة: صفحة مستقلة هي ما يُقرأ آليًّا.
            if (preg_match('/<a[^>]*>[^<]*'.preg_quote($term, '/').'[^<]*<\/a>/u', $html)) {
                $found[$label] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * بنية الصفحة العربية: اللغة والاتجاه وتدرّج العناوين.
     *
     * ثلاث درجات لا اثنتان: «ناقص» حال حقيقية شائعة — موقع بلغة صحيحة وعناوين
     * فوضوية — واختزالها إلى نجاح أو فشل يخفي ما يمكن إصلاحه هذا الأسبوع.
     */
    private function arabicStructure(string $html): string
    {
        $signals = 0;

        if (preg_match('/<html[^>]*lang\s*=\s*"ar/i', $html)) {
            $signals++;
        }

        if (preg_match('/<html[^>]*dir\s*=\s*"rtl"/i', $html)) {
            $signals++;
        }

        if (preg_match_all('/<h1[\s>]/i', $html) === 1 && preg_match('/<h2[\s>]/i', $html)) {
            $signals++;
        }

        return match (true) {
            $signals >= 3 => 'good',
            $signals >= 1 => 'partial',
            default => 'poor',
        };
    }

    /**
     * بوتات الذكاء مسموحة ما لم يمنعها robots.txt صراحةً.
     *
     * غياب الملف يعني السماح، لا المنع — وهو ما يقوله المعيار نفسه.
     */
    private function botsAllowed(?string $robots): bool
    {
        if ($robots === null || trim($robots) === '') {
            return true;
        }

        foreach ($this->blockedAgents($robots) as $agent) {
            foreach (self::AI_BOTS as $bot) {
                if (strcasecmp($agent, $bot) === 0 || $agent === '*') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * الوكلاء الممنوعون من الجذر.
     *
     * `Disallow: /` وحده هو المنع الكامل. منع مسار فرعي لا يخرجك من الإجابات،
     * واعتباره منعًا يجعل التقرير يتّهم إعدادًا سليمًا.
     *
     * @return array<int, string>
     */
    private function blockedAgents(string $robots): array
    {
        $blocked = [];
        $current = [];

        // فواصل مسمّاة لا `\R`: الأخير يشطر الحروف العربية في التعليقات، و`u`
        // يجعل ملفًا فيه بايت غير صالح يُقرأ كأنه فارغ.
        foreach (preg_split('/\r\n|\r|\n/', $robots) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '') {
                $current = [];

                continue;
            }

            if (preg_match('/^user-agent\s*:\s*(.+)$/i', $line, $match)) {
                $current[] = trim($match[1]);

                continue;
            }

            if (preg_match('/^disallow\s*:\s*(.*)$/i', $line, $match) && trim($match[1]) === '/') {
                $blocked = [...$blocked, ...$current];
            }
        }

        return array_values(array_unique($blocked));
    }
}
