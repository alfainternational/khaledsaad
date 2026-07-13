<?php

namespace App\Domain\AI\Web;

use App\Contracts\WebSearchGateway;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Knowledge\EmbeddingJobDispatcher;
use App\Domain\AI\Services\AiMetrics;
use App\Domain\AI\Web\Models\WebResearchResult;
use App\Domain\AI\Web\Models\WebResearchRun;
use App\Support\Intelligence\RemotePageFetcher;
use Carbon\CarbonImmutable;
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
        private readonly WebPageExtractor $pageExtractor,
        private readonly WebSourcePolicy $sourcePolicy,
        private readonly WebKnowledgeIngestor $webKnowledge,
        private readonly EmbeddingJobDispatcher $embeddings,
        private readonly WebSearchResultNormalizer $resultNormalizer,
        private readonly WebClaimVerificationDispatcher $claimVerification,
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

        if ((bool) config('services.web_search.verified_research', false)) {
            return $this->verifiedResearch($query, $depth);
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

    /** @return array<string, mixed> */
    private function verifiedResearch(string $query, int $depth): array
    {
        $depth = max(1, min((int) config('services.web_search.max_results', 8), $depth));
        $run = WebResearchRun::query()->create([
            'public_id' => (string) Str::uuid(),
            'query' => $query,
            'query_hash' => hash('sha256', mb_strtolower($query)),
            'status' => 'running',
            'requested_depth' => $depth,
            'started_at' => now(),
        ]);

        try {
            $results = $this->search->search($query, (int) config('services.web_search.max_results', 8));
            $fetchLimit = min($depth, max(1, (int) config('services.web_search.max_fetches_per_run', 3)));
            $findings = [];

            foreach (array_slice($results, 0, $fetchLimit) as $index => $result) {
                $result['rank'] = $index + 1;
                $fetch = $this->fetcher->fetch((string) ($result['url'] ?? ''));
                if (! ($fetch['ok'] ?? false) || ! is_string($fetch['html'] ?? null)) {
                    $this->recordFailure($run, $result, $fetch, (string) ($fetch['error'] ?? 'fetch_failed'));

                    continue;
                }

                try {
                    $page = $this->pageExtractor->extract((string) $fetch['html'], (string) $fetch['url']);
                    $publishedAt = filled($page['published_at']) ? CarbonImmutable::parse($page['published_at']) : null;
                    $policy = $this->sourcePolicy->assess((string) $page['canonical_url'], $publishedAt);
                    $stored = $this->webKnowledge->ingest($run, $result, $fetch, $page, $policy);
                } catch (\Throwable $exception) {
                    $this->recordFailure($run, $result, $fetch, Str::limit($exception->getMessage(), 80, ''));

                    continue;
                }

                $findings[] = [
                    'title' => $stored->title,
                    'url' => $stored->normalized_url,
                    'snippet' => $stored->snippet,
                    'category' => $this->classify($stored->title.' '.$stored->snippet),
                    'relevance' => $this->relevance($stored->title.' '.$stored->snippet, $this->terms($query)),
                    'trust_tier' => $stored->trust_tier,
                    'trust_score' => $stored->trust_score,
                    'freshness_status' => $stored->freshness_status,
                    'verification_status' => $stored->verification_status,
                    'citation' => $stored->meta_json['citation'],
                ];
            }

            $categories = [];
            foreach ($findings as $finding) {
                $categories[$finding['category']] = ($categories[$finding['category']] ?? 0) + 1;
            }
            arsort($categories);

            $run->update([
                'status' => 'completed',
                'result_count' => count($findings),
                'verified_count' => 0,
                'conflict_count' => 0,
                'completed_at' => now(),
            ]);
            if ((bool) config('services.private_worker.enabled', false) && $findings !== []) {
                $this->embeddings->dispatch(count($findings) * 4);
            }
            if (count($findings) >= 2) {
                $this->claimVerification->dispatch($run->fresh());
            }
            $this->metrics->incr($findings === [] ? 'web.fail' : 'web.search');

            return [
                'query' => $query,
                'findings' => $findings,
                'categories' => $categories,
                'summary' => $this->summarize($query, $findings, $categories),
                'researched_at' => now()->toIso8601String(),
                'research_run_id' => $run->public_id,
            ];
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_code' => 'research_failed',
                'completed_at' => now(),
                'checkpoint_json' => ['exception' => $exception::class],
            ]);
            $this->metrics->incr('web.fail');

            return [
                'query' => $query,
                'findings' => [],
                'categories' => [],
                'summary' => 'تعذّر الوصول إلى أدلة حيّة موثقة الآن.',
                'research_run_id' => $run->public_id,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $fetch
     */
    private function recordFailure(WebResearchRun $run, array $result, array $fetch, string $error): void
    {
        $originalUrl = (string) ($result['url'] ?? '');
        $normalizedUrl = $this->resultNormalizer->normalizeUrl($originalUrl);
        if ($normalizedUrl === null) {
            return;
        }

        WebResearchResult::query()->updateOrCreate(
            [
                'web_research_run_id' => $run->id,
                'normalized_url_hash' => hash('sha256', $normalizedUrl),
            ],
            [
                'provider' => (string) ($result['provider'] ?? 'unknown'),
                'rank' => max(1, (int) ($result['rank'] ?? 1)),
                'title' => (string) ($result['title'] ?? ''),
                'original_url' => $originalUrl,
                'normalized_url' => $normalizedUrl,
                'domain' => strtolower((string) parse_url($normalizedUrl, PHP_URL_HOST)),
                'snippet' => (string) ($result['snippet'] ?? ''),
                'fetch_status' => 'failed',
                'http_status' => isset($fetch['status']) ? (int) $fetch['status'] : null,
                'trust_tier' => 'unknown',
                'trust_score' => 0,
                'freshness_status' => 'unknown',
                'verification_status' => 'unverified',
                'fetched_at' => now(),
                'meta_json' => ['error' => $error],
            ],
        );
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
