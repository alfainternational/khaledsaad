<?php

namespace App\Domain\AI\Web;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;

class WebPageExtractor
{
    /**
     * @return array{title: string, canonical_url: string, language: string, published_at: ?string, text: string, content_hash: string}
     */
    public function extract(string $html, string $fetchedUrl): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new InvalidArgumentException('web_page_invalid_html');
        }

        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//script|//style|//noscript|//template|//svg') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $text = $this->normalize((string) ($xpath->query('//body')->item(0)?->textContent ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('web_page_has_no_text');
        }

        $title = $this->normalize((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));
        $language = strtolower(trim((string) $document->documentElement?->getAttribute('lang')));
        $language = preg_replace('/[^a-z-]/', '', $language) ?: 'und';
        $language = substr($language, 0, 12);

        return [
            'title' => $title,
            'canonical_url' => $this->canonicalUrl($xpath, $fetchedUrl),
            'language' => $language,
            'published_at' => $this->publishedAt($xpath),
            'text' => $text,
            'content_hash' => hash('sha256', $text),
        ];
    }

    private function canonicalUrl(\DOMXPath $xpath, string $fetchedUrl): string
    {
        $node = $xpath->query('//link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " canonical ")]')->item(0);
        $href = trim((string) $node?->getAttribute('href'));
        if ($href === '') {
            return $fetchedUrl;
        }

        try {
            $resolved = (string) UriResolver::resolve(new Uri($fetchedUrl), new Uri($href));

            return filter_var($resolved, FILTER_VALIDATE_URL) ? $resolved : $fetchedUrl;
        } catch (\Throwable) {
            return $fetchedUrl;
        }
    }

    private function publishedAt(\DOMXPath $xpath): ?string
    {
        $queries = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@name="date"]/@content',
            '//time[@datetime][1]/@datetime',
        ];

        foreach ($queries as $query) {
            $value = trim((string) ($xpath->query($query)->item(0)?->nodeValue ?? ''));
            if ($value === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($value)->utc()->toIso8601String();
            } catch (\Throwable) {
                // Try the next explicit publication-date field.
            }
        }

        return null;
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
