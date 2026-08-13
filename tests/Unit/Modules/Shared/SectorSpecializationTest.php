<?php

namespace Tests\Unit\Modules\Shared;

use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\AiReadiness\SiteAudit;
use App\Modules\AiReadiness\SiteAuditResult;
use App\Modules\Shared\Sectors\Sector;
use App\Support\Kpis\KpiTemplates;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * بوابة التخصص القطاعي الثلاثي (مواصفة SECTOR-SPECIALIZATION.md).
 *
 * تحرس القاعدتين اللتين يقوم عليهما التخصص: القطاعات القانونية ثلاثة لا
 * رابع لها، والفحص القطاعي يغيّر النصيحة لا مفتاح الحقيقة.
 */
class SectorSpecializationTest extends TestCase
{
    #[Test]
    public function the_specialized_sectors_are_exactly_three(): void
    {
        $this->assertSame(['education', 'ecommerce', 'real_estate'], Sector::SPECIALIZED);
        $this->assertSame([...Sector::SPECIALIZED, 'other'], Sector::DECLARABLE);
    }

    #[Test]
    public function declared_or_general_accepts_specialized_and_rejects_everything_else(): void
    {
        $this->assertSame('education', Sector::declaredOrGeneral('education'));
        $this->assertSame('general', Sector::declaredOrGeneral('other'));
        $this->assertSame('general', Sector::declaredOrGeneral(null));
        $this->assertSame('general', Sector::declaredOrGeneral('saas'));
    }

    #[Test]
    public function every_declarable_sector_has_a_label_and_an_option(): void
    {
        foreach (Sector::DECLARABLE as $sector) {
            $this->assertNotSame('غير محدد', Sector::label($sector));
        }

        $this->assertSame(Sector::DECLARABLE, array_column(Sector::options(), 'value'));
    }

    /**
     * مدرسة فيها Course schema تنجح في بند العرض، ولا تُنصح بإضافة Product.
     */
    #[Test]
    public function the_site_audit_checks_course_schema_for_education(): void
    {
        $audit = new SiteAudit($this->fetcherReturning(
            '<html lang="ar" dir="rtl"><script type="application/ld+json">{"@type": "Course"}</script></html>',
        ));

        $result = $audit->audit('https://school.example', Sector::EDUCATION);

        $this->assertTrue($result->schemaProducts);
        $this->assertSame('schema_products', $result->checklist()[1]['key'], 'مفتاح الحقيقة ثابت مهما تغيّر القطاع.');
        $this->assertStringContainsString('Course', $result->checklist()[1]['fix']);

        // المتجر نفسه بنفس الصفحة لا يُحتسب له Course مكان Product.
        $asStore = $audit->audit('https://school.example', Sector::ECOMMERCE);
        $this->assertFalse($asStore->schemaProducts);
    }

    #[Test]
    public function the_site_audit_checks_listing_schema_for_real_estate(): void
    {
        $audit = new SiteAudit($this->fetcherReturning(
            '<html><script type="application/ld+json">{"@type": "RealEstateListing"}</script></html>',
        ));

        $result = $audit->audit('https://broker.example', Sector::REAL_ESTATE);

        $this->assertTrue($result->schemaProducts);
        $this->assertStringContainsString('RealEstateListing', $result->checklist()[1]['fix']);
    }

    #[Test]
    public function an_undeclared_sector_keeps_the_product_check_unchanged(): void
    {
        $audit = new SiteAudit($this->fetcherReturning(
            '<html><script type="application/ld+json">{"@type": "Product"}</script></html>',
        ));

        $result = $audit->audit('https://shop.example');

        $this->assertTrue($result->schemaProducts);
        $this->assertStringContainsString('Product', $result->checklist()[1]['fix']);
    }

    #[Test]
    public function sector_result_facts_keys_do_not_change(): void
    {
        $result = new SiteAuditResult(
            url: 'https://x.example',
            reachable: true,
            schemaOrganization: true,
            schemaProducts: true,
            pricesMachineReadable: false,
            policyPages: [],
            arabicPageStructure: 'good',
            llmsTxt: false,
            aiBotsAllowed: true,
            sector: Sector::EDUCATION,
        );

        $this->assertSame(
            ['schema_organization', 'schema_products', 'prices_machine_readable', 'policy_pages', 'arabic_page_structure', 'llms_txt', 'ai_bots_allowed'],
            array_keys($result->facts()),
            'مفاتيح حقائق الدماغ ثابتة — تغييرها يقطع التاريخ التراكمي (§٤ من المواصفة).',
        );
    }

    #[Test]
    public function kpi_templates_lead_with_the_sector_group_for_specialized_sectors_only(): void
    {
        $this->assertSame('التسجيل والطلاب', KpiTemplates::catalog(Sector::EDUCATION)[0]['group']);
        $this->assertSame('متجرك', KpiTemplates::catalog(Sector::ECOMMERCE)[0]['group']);
        $this->assertSame('استفساراتك ومعايناتك', KpiTemplates::catalog(Sector::REAL_ESTATE)[0]['group']);

        $this->assertSame('مبيعات وإيراد', KpiTemplates::catalog()[0]['group']);
        $this->assertSame('مبيعات وإيراد', KpiTemplates::catalog('other')[0]['group']);
        $this->assertCount(count(KpiTemplates::catalog()), KpiTemplates::catalog('other'), 'قطاع غير متخصص لا يفقد شيئًا من القالب العام.');
    }

    private function fetcherReturning(string $html): PageFetcher
    {
        return new class($html) implements PageFetcher
        {
            public function __construct(private readonly string $html) {}

            public function get(string $url): ?string
            {
                // robots.txt وllms.txt غائبان — لا يعنيان الفحص هنا.
                return str_contains($url, '.txt') ? null : $this->html;
            }
        };
    }
}
