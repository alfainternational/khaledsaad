<?php

namespace App\Domain\AI\Web;

use App\Contracts\WebSearchGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * بحث حيّ بلا مفتاح API عبر DuckDuckGo HTML (POST بـ browser UA).
 *
 * النطاق ثابت وموثوق (html.duckduckgo.com) والاستعلام مُرمَّز كـ param، فلا خطر
 * SSRF هنا؛ أما صفحات النتائج فتُجلب لاحقاً عبر RemotePageFetcher المحمي.
 * يتدهور بأمان: يعيد [] عند أي فشل. العقد مجرّد لاستبداله بمزوّد بمفتاح لاحقاً.
 */
class DuckDuckGoSearchGateway implements WebSearchGateway
{
    private const ENDPOINT = 'https://html.duckduckgo.com/html/';

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::UA,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ar,en;q=0.8',
            ])->asForm()->timeout(15)->connectTimeout(8)->post(self::ENDPOINT, ['q' => $query]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->parse($response->body(), $limit);
    }

    /**
     * @return array<int, array{title: string, url: string, snippet: string}>
     */
    private function parse(string $html, int $limit): array
    {
        if (stripos($html, 'challenge') !== false && stripos($html, 'result__a') === false) {
            return [];
        }

        $results = [];

        if (preg_match_all('/class="result__a"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/si', $html, $linkMatches, PREG_SET_ORDER)) {
            preg_match_all('/class="result__snippet"[^>]*>(.*?)<\/a>/si', $html, $snippetMatches);
            $snippets = $snippetMatches[1] ?? [];

            foreach ($linkMatches as $i => $m) {
                if (count($results) >= $limit) {
                    break;
                }

                $url = $this->decodeUrl(html_entity_decode($m[1], ENT_QUOTES));
                if ($url === null) {
                    continue;
                }

                $results[] = [
                    'title' => $this->clean($m[2]),
                    'url' => $url,
                    'snippet' => isset($snippets[$i]) ? $this->clean((string) $snippets[$i]) : '',
                ];
            }
        }

        return $results;
    }

    private function decodeUrl(string $href): ?string
    {
        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        $parts = parse_url($href);
        parse_str($parts['query'] ?? '', $q);
        if (isset($q['uddg']) && is_string($q['uddg'])) {
            $href = $q['uddg'];
        } elseif (str_ends_with(strtolower((string) ($parts['host'] ?? '')), 'duckduckgo.com')) {
            return null;
        }

        return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
    }

    private function clean(string $raw): string
    {
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
