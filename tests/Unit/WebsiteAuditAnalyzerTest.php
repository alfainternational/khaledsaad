<?php

namespace Tests\Unit;

use App\Support\Intelligence\OfficialContactExtractor;
use App\Support\Intelligence\RemotePageFetcher;
use App\Support\Intelligence\WebsiteAuditAnalyzer;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebsiteAuditAnalyzerTest extends TestCase
{
    #[Test]
    public function it_extracts_actionable_findings_from_a_weak_primary_page(): void
    {
        Http::fake([
            'https://weak-site.test' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>Weak</title>
                    </head>
                    <body>
                        <img src="/hero.jpg">
                        <p>General words with no real CTA or service structure.</p>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $analyzer = new WebsiteAuditAnalyzer(new RemotePageFetcher, new OfficialContactExtractor);
        $result = $analyzer->analyze('weak-site.test', 'b2b_services');

        $titles = collect($result['findings'])->pluck('title')->all();

        $this->assertFalse($result['snapshot']['ok'] === false);
        $this->assertContains('عنوان الصفحة الأساسية ضعيف أو ناقص', $titles);
        $this->assertContains('الوصف التعريفي غير كافٍ', $titles);
        $this->assertContains('الصفحة الأساسية بلا H1 واضح', $titles);
        $this->assertContains('توافق الجوال غير مضمون', $titles);
        $this->assertContains('لا يوجد CTA واضح في المحتوى الظاهر', $titles);
        $this->assertContains('عمق صفحات الخدمات محدود', $titles);
        $this->assertContains('وسائل التواصل غير واضحة بما يكفي', $titles);
        $this->assertContains('إمكانية الوصول تحتاج تحسيناً', $titles);
    }

    #[Test]
    public function it_does_not_report_missing_https_when_the_site_redirects_to_https(): void
    {
        Http::fake([
            'http://redirected-site.test' => Http::response('', 301, [
                'Location' => 'https://redirected-site.test',
            ]),
            'https://redirected-site.test' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>Redirected Site With HTTPS</title>
                        <meta name="description" content="وصف واضح بما يكفي لتجاوز فحص الوصف التعريفي في هذا الاختبار.">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                    </head>
                    <body>
                        <h1>عرض واضح</h1>
                        <a href="/services">Services</a>
                        <a href="/contact">Contact</a>
                        <a href="/privacy">Privacy</a>
                        <a href="/terms">Terms</a>
                        <p>Get started and contact us today.</p>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $analyzer = new WebsiteAuditAnalyzer(new RemotePageFetcher, new OfficialContactExtractor);
        $result = $analyzer->analyze('http://redirected-site.test', 'b2b_services');

        $titles = collect($result['findings'])->pluck('title')->all();

        $this->assertSame('https://redirected-site.test', $result['snapshot']['url']);
        $this->assertNotContains('الموقع لا يعمل عبر HTTPS', $titles);
    }
}
