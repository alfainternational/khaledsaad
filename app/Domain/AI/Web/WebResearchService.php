<?php

namespace App\Domain\AI\Web;

use App\Contracts\WebSearchGateway;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Services\AiMetrics;
use App\Support\Intelligence\RemotePageFetcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * بحث وتحليل لحظي على الإنترنت + تصنيف + تنمية المعرفة.
 *
 * يعمل داخل الطلب (real-time عند طلب المستخدم)، بكاش قصير لحماية الموارد —
 * بلا عملية خلفية. كل بحث يُغذّي KnowledgeStore فتنمو معرفة النظام مع الاستخدام.
 */
class WebResearchService
{
    /** تصنيفات تسويقية بكلمات دالة (عربي/إنجليزي). */
    private const CATEGORIES = [
        'pricing' => ['سعر', 'أسعار', 'تسعير', 'باقة', 'اشتراك', 'price', 'pricing', 'cost', 'plan'],
        'competitor' => ['منافس', 'منافسين', 'بديل', 'مقارنة', 'competitor', 'alternative', 'vs', 'compare'],
        'market' => ['سوق', 'اتجاه', 'نمو', 'طلب', 'حجم', 'market', 'trend', 'growth', 'demand', 'industry'],
        'audience' => ['جمهور', 'عميل', 'فئة', 'شريحة', 'audience', 'customer', 'segment', 'persona'],
        'channel' => ['قناة', 'إعلان', 'حملة', 'سوشيال', 'channel', 'ads', 'campaign', 'social', 'seo'],
    ];

    public function __construct(
        private readonly WebSearchGateway $search,
        private readonly RemotePageFetcher $fetcher,
        private readonly KnowledgeStore $knowledge,
        private readonly AiMetrics $metrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function research(string $query, int $depth = 3): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['query' => '', 'findings' => [], 'categories' => [], 'summary' => 'لا يوجد استعلام للبحث.'];
        }

        $cacheKey = 'web_research:'.hash('sha256', $query.'|'.$depth);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($query, $depth): array {
            $results = $this->search->search($query, max(3, $depth + 2));

            if ($results === []) {
                $this->metrics->incr('web.fail');

                return [
                    'query' => $query,
                    'findings' => [],
                    'categories' => [],
                    'summary' => 'تعذّر الوصول لنتائج حيّة الآن — حاول لاحقاً أو بصياغة مختلفة.',
                ];
            }

            $this->metrics->incr('web.search');
            $terms = $this->terms($query);
            $findings = [];

            foreach (array_slice($results, 0, $depth) as $result) {
                $excerpt = $this->extract($result['url']);
                $text = $result['title'].' '.$result['snippet'].' '.$excerpt;
                $findings[] = [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'snippet' => $result['snippet'] !== '' ? $result['snippet'] : Str::limit($excerpt, 200, '…'),
                    'category' => $this->classify($text),
                    'relevance' => $this->relevance($text, $terms),
                ];
            }

            // الباقي بلا جلب عميق (عنوان+مقتطف فقط) لإثراء التغطية بتكلفة أقل.
            foreach (array_slice($results, $depth) as $result) {
                $text = $result['title'].' '.$result['snippet'];
                $findings[] = [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'snippet' => $result['snippet'],
                    'category' => $this->classify($text),
                    'relevance' => $this->relevance($text, $terms),
                ];
            }

            usort($findings, fn (array $a, array $b): int => $b['relevance'] <=> $a['relevance']);

            $categories = [];
            foreach ($findings as $f) {
                $categories[$f['category']] = ($categories[$f['category']] ?? 0) + 1;
            }
            arsort($categories);

            $payload = [
                'query' => $query,
                'findings' => $findings,
                'categories' => $categories,
                'summary' => $this->summarize($query, $findings, $categories),
                'researched_at' => now()->toIso8601String(),
            ];

            // تنمية المعرفة: كل بحث يُخزَّن فتتراكم معرفة النظام عبر الزمن.
            $this->knowledge->remember('web.'.Str::slug($query, '_', 'ar') ?: 'web.query', [
                'query' => $query,
                'top_category' => array_key_first($categories) ?? 'general',
                'sources' => array_map(fn (array $f): string => (string) $f['url'], array_slice($findings, 0, 5)),
            ]);

            return $payload;
        });
    }

    private function extract(string $url): string
    {
        $response = $this->fetcher->fetch($url);
        if (! ($response['ok'] ?? false) || ! is_string($response['html'] ?? null)) {
            return '';
        }

        $html = (string) $response['html'];
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 600, '…');
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function relevance(string $text, array $terms): int
    {
        $haystack = mb_strtolower($text);
        $score = 0;
        foreach ($terms as $term) {
            if ($term !== '' && mb_strpos($haystack, $term) !== false) {
                $score++;
            }
        }

        return $score;
    }

    private function classify(string $text): string
    {
        $haystack = mb_strtolower($text);
        $best = 'general';
        $bestHits = 0;
        foreach (self::CATEGORIES as $category => $keywords) {
            $hits = 0;
            foreach ($keywords as $kw) {
                if (mb_strpos($haystack, mb_strtolower($kw)) !== false) {
                    $hits++;
                }
            }
            if ($hits > $bestHits) {
                $bestHits = $hits;
                $best = $category;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @param  array<string, int>  $categories
     */
    private function summarize(string $query, array $findings, array $categories): string
    {
        if ($findings === []) {
            return 'لا نتائج.';
        }

        $labels = [
            'pricing' => 'التسعير', 'competitor' => 'المنافسين', 'market' => 'السوق',
            'audience' => 'الجمهور', 'channel' => 'القنوات', 'general' => 'عام',
        ];
        $top = array_key_first($categories);

        return sprintf(
            'وجدت %d مصدراً حول «%s»، أغلبها يخصّ %s. أهم مصدر: %s',
            count($findings), $query, $labels[$top] ?? $top, (string) $findings[0]['title'],
        );
    }

    /**
     * @return array<int, string>
     */
    private function terms(string $text): array
    {
        $text = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '');

        return collect(preg_split('/\s+/u', $text) ?: [])
            ->filter(fn (string $w): bool => mb_strlen($w) >= 3)
            ->unique()->take(15)->values()->all();
    }
}
