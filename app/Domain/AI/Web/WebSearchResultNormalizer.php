<?php

namespace App\Domain\AI\Web;

class WebSearchResultNormalizer
{
    /**
     * @param  array<string, mixed>  $result
     * @return array{title: string, url: string, snippet: string}|null
     */
    public function normalize(array $result): ?array
    {
        $url = $this->normalizeUrl((string) ($result['url'] ?? ''));
        if ($url === null) {
            return null;
        }

        return [
            'title' => $this->text((string) ($result['title'] ?? '')),
            'url' => $url,
            'snippet' => $this->text((string) ($result['snippet'] ?? '')),
        ];
    }

    public function normalizeUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $portText = $port !== null && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
            ? ':'.$port
            : '';
        $path = $parts['path'] ?? '/';
        $path = $path === '/' ? '' : rtrim($path, '/');

        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/^(utm_.+|gclid|fbclid|msclkid|ref)$/i', (string) $key) === 1) {
                unset($query[$key]);
            }
        }
        ksort($query, SORT_STRING);
        $queryText = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $scheme.'://'.$host.$portText.$path.($queryText !== '' ? '?'.$queryText : '');
    }

    private function text(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
