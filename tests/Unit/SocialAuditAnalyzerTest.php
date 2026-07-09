<?php

namespace Tests\Unit;

use App\Support\Intelligence\RemotePageFetcher;
use App\Support\Intelligence\SocialAuditAnalyzer;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SocialAuditAnalyzerTest extends TestCase
{
    #[Test]
    public function it_records_limited_confidence_findings_when_social_profiles_are_inaccessible(): void
    {
        Http::fake([
            'https://instagram.com/blocked-profile' => Http::response('', 403),
        ]);

        $analyzer = new SocialAuditAnalyzer(new RemotePageFetcher);
        $result = $analyzer->analyze(['https://instagram.com/blocked-profile'], 'example.com');

        $this->assertSame(1, $result['analysis_meta']['requested_profiles']);
        $this->assertSame(0, $result['analysis_meta']['accessible_profiles']);
        $this->assertSame(1, $result['analysis_meta']['failed_profiles']);
        $this->assertSame('تعذّرت قراءة صفحة Instagram العامة', $result['findings'][0]['title']);
        $this->assertSame(0.45, $result['findings'][0]['confidence']);
    }

    #[Test]
    public function it_reads_open_graph_metadata_when_standard_meta_tags_are_missing(): void
    {
        Http::fake([
            'https://www.linkedin.com/in/example/' => Http::sequence()
                ->push('', 403, ['Content-Type' => 'text/html'])
                ->push(<<<'HTML'
                    <html>
                        <head>
                            <meta property="og:title" content="Example Founder">
                            <meta property="og:description" content="نبني مسارات تسويق أوضح للشركات العربية من خلال تشخيص ورسائل وتنفيذ مترابط.">
                            <link rel="canonical" href="https://www.linkedin.com/in/example/">
                        </head>
                        <body>
                            زوروا https://khaledsaad.net لمعرفة المزيد.
                        </body>
                    </html>
                HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $analyzer = new SocialAuditAnalyzer(new RemotePageFetcher);
        $result = $analyzer->analyze(['https://www.linkedin.com/in/example/'], 'https://khaledsaad.net');

        $this->assertCount(1, $result['profiles']);
        $this->assertSame('Example Founder', $result['profiles'][0]['title']);
        $this->assertSame(
            'نبني مسارات تسويق أوضح للشركات العربية من خلال تشخيص ورسائل وتنفيذ مترابط.',
            $result['profiles'][0]['description'],
        );
        $this->assertTrue($result['profiles'][0]['links_back_to_site']);
        $this->assertCount(2, $result['profiles'][0]['attempts']);
    }

    #[Test]
    public function it_uses_manual_verified_profiles_when_automatic_fetch_is_blocked(): void
    {
        Http::fake([
            'https://x.com/example' => Http::sequence()
                ->push('', 403, ['Content-Type' => 'text/html'])
                ->push('', 403, ['Content-Type' => 'text/html']),
        ]);

        $analyzer = new SocialAuditAnalyzer(new RemotePageFetcher);
        $result = $analyzer->analyze(
            ['https://x.com/example'],
            'https://khaledsaad.net',
            [[
                'network' => 'X',
                'url' => 'https://x.com/example',
                'handle' => '@example',
                'title' => 'Example Account',
                'description' => 'حساب موثق يدوياً بعد تعذر القراءة الآلية.',
                'primary_cta' => 'راسلنا عبر الموقع',
                'links_back_to_site' => true,
                'verification_notes' => 'Verified manually by workspace owner',
            ]],
        );

        $this->assertCount(1, $result['profiles']);
        $this->assertSame('manual', $result['profiles'][0]['verification_source']);
        $this->assertSame('Example Account', $result['profiles'][0]['title']);
        $this->assertTrue($result['profiles'][0]['links_back_to_site']);
        $this->assertSame(1, $result['analysis_meta']['manual_verified_profiles']);
        $this->assertSame(0, $result['analysis_meta']['automated_accessible_profiles']);
    }

    #[Test]
    public function it_counts_manual_verified_profiles_without_urls_as_valid_social_sources(): void
    {
        $analyzer = new SocialAuditAnalyzer(new RemotePageFetcher);
        $result = $analyzer->analyze(
            [],
            null,
            [[
                'network' => 'Instagram',
                'handle' => '@socialonly',
                'title' => 'Social Only Brand',
                'description' => 'حساب موثق يدوياً حتى قبل ربط URL نهائي داخل المشروع.',
                'primary_cta' => 'راسلنا مباشرة',
                'links_back_to_site' => false,
                'verification_notes' => 'Verified manually from official business card',
            ]],
        );

        $this->assertSame(1, $result['analysis_meta']['requested_profiles']);
        $this->assertSame(1, $result['analysis_meta']['accessible_profiles']);
        $this->assertSame(1, $result['analysis_meta']['manual_verified_profiles']);
        $this->assertSame('manual', $result['profiles'][0]['verification_source']);
        $this->assertSame('@socialonly', $result['profiles'][0]['handle']);
        $this->assertFalse(collect($result['findings'])->contains(
            fn (array $finding): bool => $finding['subcategory'] === 'presence'
        ));
    }
}
