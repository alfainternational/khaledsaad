<?php

namespace Tests\Unit\AI\Web;

use App\Contracts\WebSearchGateway;
use App\Domain\AI\Web\CompositeWebSearchGateway;
use App\Domain\AI\Web\WebSearchResultNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompositeWebSearchGatewayTest extends TestCase
{
    #[Test]
    public function it_normalizes_deduplicates_and_diversifies_results_across_providers(): void
    {
        $first = $this->gateway([
            ['title' => 'A', 'url' => 'https://Example.com/page/?utm_source=x&b=2&a=1#top', 'snippet' => 'one'],
            ['title' => 'A duplicate', 'url' => 'https://example.com/page?a=1&b=2', 'snippet' => 'duplicate'],
            ['title' => 'B', 'url' => 'https://example.com/two', 'snippet' => 'two'],
            ['title' => 'C', 'url' => 'https://example.com/three', 'snippet' => 'three'],
        ]);
        $failed = new class implements WebSearchGateway
        {
            public function search(string $query, int $limit = 5): array
            {
                throw new \RuntimeException('provider unavailable');
            }
        };
        $second = $this->gateway([
            ['title' => 'D', 'url' => 'https://official.test/report', 'snippet' => 'four'],
        ]);

        $gateway = new CompositeWebSearchGateway(
            ['first' => $first, 'failed' => $failed, 'second' => $second],
            new WebSearchResultNormalizer,
            2,
        );

        $results = $gateway->search('market', 4);

        $this->assertSame([
            'https://example.com/page?a=1&b=2',
            'https://example.com/two',
            'https://official.test/report',
        ], array_column($results, 'url'));
        $this->assertSame(['first', 'first', 'second'], array_column($results, 'provider'));
    }

    private function gateway(array $results): WebSearchGateway
    {
        return new class($results) implements WebSearchGateway
        {
            public function __construct(private readonly array $results) {}

            public function search(string $query, int $limit = 5): array
            {
                return array_slice($this->results, 0, $limit);
            }
        };
    }
}
