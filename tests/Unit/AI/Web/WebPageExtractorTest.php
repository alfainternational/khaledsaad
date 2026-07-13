<?php

namespace Tests\Unit\AI\Web;

use App\Domain\AI\Web\WebPageExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebPageExtractorTest extends TestCase
{
    #[Test]
    public function it_extracts_citable_visible_page_evidence(): void
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="ar"><head>
<title>تقرير السوق</title>
<link rel="canonical" href="https://example.com/reports/market">
<meta property="article:published_time" content="2026-07-10T08:30:00+03:00">
<style>.hidden { display:none }</style><script>ignore me</script>
</head><body><main><h1>نمو السوق</h1><p>بلغ النمو السنوي 12 بالمئة.</p></main></body></html>
HTML;

        $page = (new WebPageExtractor)->extract($html, 'https://example.com/raw?id=1');

        $this->assertSame('تقرير السوق', $page['title']);
        $this->assertSame('https://example.com/reports/market', $page['canonical_url']);
        $this->assertSame('ar', $page['language']);
        $this->assertSame('2026-07-10T05:30:00+00:00', $page['published_at']);
        $this->assertStringContainsString('بلغ النمو السنوي 12 بالمئة', $page['text']);
        $this->assertStringNotContainsString('ignore me', $page['text']);
        $this->assertSame(hash('sha256', $page['text']), $page['content_hash']);
    }

    #[Test]
    public function it_rejects_pages_without_meaningful_visible_text(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('web_page_has_no_text');

        (new WebPageExtractor)->extract('<html><script>only code</script></html>', 'https://example.com');
    }
}
