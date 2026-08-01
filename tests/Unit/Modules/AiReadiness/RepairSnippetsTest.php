<?php

namespace Tests\Unit\Modules\AiReadiness;

use App\Modules\AiReadiness\RepairSnippets;
use App\Modules\AiReadiness\SiteAuditResult;
use App\Modules\Shared\Sectors\Sector;
use PHPUnit\Framework\TestCase;

/**
 * القصاصة الجاهزة: البند الذي يُصلَح بنصّ معياري ثابت لا يُترك وصفًا.
 */
class RepairSnippetsTest extends TestCase
{
    private RepairSnippets $snippets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snippets = new RepairSnippets;
    }

    /**
     * كل بند في بطاقة الجاهزية له قصاصة. بندٌ يُقاس ويُعرض بلا مادة تنفيذ
     * هو نصف مخرج — يخبر المستخدم أنه مكسور ولا يعطيه ما يصلحه به.
     */
    public function test_every_audit_item_has_a_snippet(): void
    {
        $audit = new SiteAuditResult(
            url: 'https://example.test',
            reachable: true,
            schemaOrganization: false,
            schemaProducts: false,
            pricesMachineReadable: false,
            policyPages: [],
            arabicPageStructure: 'weak',
            llmsTxt: false,
            aiBotsAllowed: false,
        );

        foreach ($audit->checklist() as $item) {
            $snippet = $this->snippets->for($item['key']);

            $this->assertIsArray($snippet, "البند {$item['key']} بلا قصاصة.");
            $this->assertNotSame('', trim($snippet['code']));
            $this->assertNotSame('', trim($snippet['where']));
        }
    }

    /**
     * القصاصة تُلصق وتُنشر كما هي، فاختراع اسم أو رقم داخلها أخطر من
     * اختراعه في تحليل. ما لا نعرفه يبقى فراغًا ظاهرًا.
     */
    public function test_snippets_leave_visible_placeholders_instead_of_invented_values(): void
    {
        $organization = $this->snippets->for('schema_organization');

        $this->assertStringContainsString('[اسم نشاطك', $organization['code']);
        $this->assertStringContainsString('[نطاقك]', $organization['code']);
    }

    /**
     * القطاع يبدّل نوع المخطط: مدرسةٌ تُنصح بـProduct نصيحة خاطئة تُفقد
     * البطاقة مصداقيتها أمام صاحبها.
     */
    public function test_the_sector_changes_the_schema_type(): void
    {
        $this->assertStringContainsString(
            '"@type": "Course"',
            $this->snippets->for('schema_products', Sector::EDUCATION)['code'],
        );

        $this->assertStringContainsString(
            '"@type": "RealEstateListing"',
            $this->snippets->for('schema_products', Sector::REAL_ESTATE)['code'],
        );

        $this->assertStringContainsString(
            '"@type": "Product"',
            $this->snippets->for('schema_products', Sector::ECOMMERCE)['code'],
        );
    }

    public function test_an_unknown_key_returns_null_rather_than_a_hollow_snippet(): void
    {
        $this->assertNull($this->snippets->for('ai_bot_visits_30d'));
    }
}
