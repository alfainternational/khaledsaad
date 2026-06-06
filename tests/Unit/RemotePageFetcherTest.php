<?php

namespace Tests\Unit;

use App\Support\Intelligence\RemotePageFetcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemotePageFetcherTest extends TestCase
{
    #[Test]
    public function it_retries_social_urls_with_a_browser_fallback_profile(): void
    {
        Http::fakeSequence()
            ->push('', 403, ['Content-Type' => 'text/html'])
            ->push('<html><head><title>Profile</title></head><body>ok</body></html>', 200, ['Content-Type' => 'text/html']);

        $fetcher = new RemotePageFetcher;
        $result = $fetcher->fetch('https://x.com/example');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertCount(2, $result['attempts']);
        $this->assertSame('standard', $result['attempts'][0]['profile']);
        $this->assertSame('browser_fallback', $result['attempts'][1]['profile']);
    }

    #[Test]
    public function it_blocks_private_targets_before_sending_the_request(): void
    {
        Http::fake();

        $fetcher = new RemotePageFetcher;
        $result = $fetcher->fetch('http://127.0.0.1/admin');

        $this->assertFalse($result['ok']);
        $this->assertSame('blocked_private_ip', $result['error']);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_blocks_redirects_to_private_targets(): void
    {
        Http::fake([
            'https://safe-site.test' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/internal',
            ]),
        ]);

        $fetcher = new RemotePageFetcher;
        $result = $fetcher->fetch('https://safe-site.test');

        $this->assertFalse($result['ok']);
        $this->assertSame('blocked_private_ip', $result['error']);
        Http::assertSentCount(1);
    }
}
