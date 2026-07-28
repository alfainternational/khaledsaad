<?php

namespace Tests\Feature;

use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\AiReadiness\SiteAudit;
use App\Modules\AiReadiness\SiteAuditResult;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التدقيق التقني ببيانات ثابتة، بلا شبكة.
 *
 * الجالب خلف عقد تحديدًا ليكون هذا ممكنًا: قواعد التدقيق تُختبر على HTML
 * مكتوب هنا، فتبقى النتيجة قابلة لإعادة الإنتاج ولا تتعلق بحال موقع خارجي.
 */
class SiteAuditTest extends TestCase
{
    #[Test]
    public function an_unreachable_site_produces_no_facts_at_all(): void
    {
        $result = $this->audit([]);

        $this->assertFalse($result->reachable);

        // الفرق الحاسم: «تعذّر الفحص» ليس «فُحص فرسب». كتابة صفر مرصود مكان
        // صفر مجهول تجعل التقرير يتّهم موقعًا سليمًا (§٤.٣).
        $this->assertSame([], $result->facts());
        $this->assertStringContainsString('ليست نتيجة فحص سلبية', $result->notes[0]);
    }

    #[Test]
    public function it_detects_structured_data_in_both_json_ld_and_microdata(): void
    {
        $jsonLd = $this->audit(['/' => '<script type="application/ld+json">{"@type":"Organization","name":"متجر"}</script>']);
        $this->assertTrue($jsonLd->schemaOrganization);

        $microdata = $this->audit(['/' => '<div itemtype="https://schema.org/LocalBusiness"></div>']);
        $this->assertTrue($microdata->schemaOrganization, 'Microdata مقروء آليًّا أيضًا.');

        $this->assertFalse($this->audit(['/' => '<p>متجرنا الرائد</p>'])->schemaOrganization);
    }

    #[Test]
    public function a_price_without_a_currency_is_not_machine_readable(): void
    {
        // رقم مجرّد قد يكون سعرًا أو عدد قطع أو رقم موديل.
        $this->assertFalse($this->audit(['/' => '{"price":"199"}'])->pricesMachineReadable);

        $this->assertTrue(
            $this->audit(['/' => '{"price":"199","priceCurrency":"SAR"}'])->pricesMachineReadable,
        );
    }

    #[Test]
    public function policy_pages_count_only_when_linked_not_merely_mentioned(): void
    {
        $mentioned = $this->audit(['/' => '<p>نلتزم بسياسة الخصوصية وسياسة الشحن</p>']);
        $this->assertSame([], $mentioned->policyPages, 'الذكر ليس صفحة.');

        $linked = $this->audit(['/' => '<a href="/p">سياسة الخصوصية</a><a href="/s">الشحن</a>']);
        $this->assertEqualsCanonicalizing(
            ['سياسة الخصوصية', 'الشحن والتوصيل'],
            $linked->policyPages,
        );
    }

    #[Test]
    public function arabic_structure_has_a_middle_state_not_just_pass_or_fail(): void
    {
        $good = $this->audit(['/' => '<html lang="ar" dir="rtl"><h1>عنوان</h1><h2>فرع</h2></html>']);
        $this->assertSame('good', $good->arabicPageStructure);

        // لغة صحيحة وعناوين فوضوية: حال شائعة، واختزالها يخفي ما يمكن إصلاحه.
        $partial = $this->audit(['/' => '<html lang="ar"><h1>أ</h1><h1>ب</h1></html>']);
        $this->assertSame('partial', $partial->arabicPageStructure);

        $this->assertSame('poor', $this->audit(['/' => '<html><p>نص</p></html>'])->arabicPageStructure);
    }

    #[Test]
    public function blocking_a_subfolder_is_not_blocking_the_bot(): void
    {
        // منع مسار إداري لا يخرجك من الإجابات؛ اعتباره منعًا يتّهم إعدادًا سليمًا.
        $partial = $this->audit([
            '/' => '<html></html>',
            '/robots.txt' => "User-agent: GPTBot\nDisallow: /admin\n",
        ]);
        $this->assertTrue($partial->aiBotsAllowed);

        $full = $this->audit([
            '/' => '<html></html>',
            '/robots.txt' => "User-agent: GPTBot\nDisallow: /\n",
        ]);
        $this->assertFalse($full->aiBotsAllowed);
    }

    #[Test]
    public function a_wildcard_root_block_shuts_every_model_out(): void
    {
        $result = $this->audit([
            '/' => '<html></html>',
            '/robots.txt' => "User-agent: *\nDisallow: /\n",
        ]);

        $this->assertFalse($result->aiBotsAllowed);
    }

    #[Test]
    public function a_missing_robots_file_means_allowed_not_blocked(): void
    {
        $result = $this->audit(['/' => '<html></html>']);

        $this->assertTrue($result->aiBotsAllowed, 'غياب الملف سماح، وهو ما يقوله المعيار.');
        $this->assertNotSame([], $result->notes);
    }

    #[Test]
    public function comments_in_robots_do_not_create_phantom_blocks(): void
    {
        $result = $this->audit([
            '/' => '<html></html>',
            '/robots.txt' => "# User-agent: GPTBot\n# Disallow: /\nUser-agent: Bingbot\nAllow: /\n",
        ]);

        $this->assertTrue($result->aiBotsAllowed);
    }

    #[Test]
    public function the_facts_it_writes_match_the_axis_registry_keys(): void
    {
        $result = $this->audit(['/' => '<html lang="ar" dir="rtl"><h1>أ</h1><h2>ب</h2></html>']);
        $registry = app(AxisRegistry::class);
        $expected = $registry->keysFor(Axis::AiReadiness);

        // عقد بين الجامع والحاسبة: مفتاح لا يعرفه السجل يضيع بلا أثر في الدرجة.
        foreach (array_keys($result->facts()) as $key) {
            $this->assertContains($key, $expected, "المفتاح {$key} لا يقرأه المحور ٧.");
        }
    }

    /**
     * @param  array<string, string>  $pages  مسار => محتوى
     */
    private function audit(array $pages): SiteAuditResult
    {
        $fetcher = new class($pages) implements PageFetcher
        {
            /** @param array<string, string> $pages */
            public function __construct(private readonly array $pages) {}

            public function get(string $url): ?string
            {
                $path = parse_url($url, PHP_URL_PATH);

                return $this->pages[$path === null || $path === '' ? '/' : $path] ?? null;
            }
        };

        return (new SiteAudit($fetcher))->audit('https://example.test');
    }
}
