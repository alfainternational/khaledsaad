<?php

namespace Tests\Unit\AI\Web;

use App\Domain\AI\Web\BingRssSearchGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BingRssSearchGatewayTest extends TestCase
{
    #[Test]
    public function it_parses_bounded_rss_results_without_an_api_key(): void
    {
        Http::fake(['https://www.bing.com/search*' => Http::response(<<<'XML'
<?xml version="1.0"?><rss><channel>
<item><title>First &amp; Official</title><link>https://one.example/report</link><description>First &lt;b&gt;summary&lt;/b&gt;</description></item>
<item><title>Second</title><link>https://two.example/report</link><description>Second summary</description></item>
</channel></rss>
XML, 200, ['Content-Type' => 'text/xml'])]);

        $results = (new BingRssSearchGateway)->search('market', 1);

        $this->assertSame([[
            'title' => 'First & Official',
            'url' => 'https://one.example/report',
            'snippet' => 'First summary',
        ]], $results);
        Http::assertSent(fn ($request): bool => $request['format'] === 'rss' && $request['q'] === 'market');
    }
}
