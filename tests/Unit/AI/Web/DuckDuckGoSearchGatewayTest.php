<?php

namespace Tests\Unit\AI\Web;

use App\Domain\AI\Web\DuckDuckGoSearchGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DuckDuckGoSearchGatewayTest extends TestCase
{
    #[Test]
    public function it_ignores_internal_ad_links_that_do_not_decode_to_a_destination(): void
    {
        Http::fake(['https://html.duckduckgo.com/html/' => Http::response(<<<'HTML'
<a class="result__a" href="https://duckduckgo.com/y.js?ad_domain=example.com">Ad</a>
<a class="result__snippet">Ad text</a>
<a class="result__a" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Fofficial.example%2Freport">Report</a>
<a class="result__snippet">Evidence</a>
HTML, 200, ['Content-Type' => 'text/html'])]);

        $this->assertSame([[
            'title' => 'Report',
            'url' => 'https://official.example/report',
            'snippet' => 'Evidence',
        ]], (new DuckDuckGoSearchGateway)->search('market', 5));
    }
}
