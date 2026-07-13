<?php

namespace App\Domain\AI\Web;

use App\Contracts\WebSearchGateway;
use Illuminate\Support\Facades\Http;

class BingRssSearchGateway implements WebSearchGateway
{
    private const ENDPOINT = 'https://www.bing.com/search';

    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; KhaledSaadIntelligenceBot/1.0)',
                'Accept' => 'application/rss+xml,application/xml;q=0.9,text/xml;q=0.8',
                'Accept-Language' => 'ar,en;q=0.8',
            ])->connectTimeout(5)->timeout(12)->get(self::ENDPOINT, [
                'format' => 'rss',
                'q' => $query,
            ]);

            if (! $response->successful()) {
                return [];
            }

            return $this->parse($response->body(), max(1, $limit));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array{title: string, url: string, snippet: string}> */
    private function parse(string $xml, int $limit): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return [];
        }

        $results = [];
        foreach ($document->getElementsByTagName('item') as $item) {
            $url = trim((string) $item->getElementsByTagName('link')->item(0)?->textContent);
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $results[] = [
                'title' => $this->clean((string) $item->getElementsByTagName('title')->item(0)?->textContent),
                'url' => $url,
                'snippet' => $this->clean((string) $item->getElementsByTagName('description')->item(0)?->textContent),
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
