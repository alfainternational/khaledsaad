<?php

namespace App\Domain\AI\Web;

use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Web\Models\WebResearchRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class WebKnowledgeRefresher
{
    public function __construct(
        private readonly \App\Support\Intelligence\RemotePageFetcher $fetcher,
        private readonly WebPageExtractor $extractor,
        private readonly WebSourcePolicy $policy,
        private readonly WebKnowledgeIngestor $ingestor,
    ) {}

    /** @return array{processed: int, updated: int, failed: int, deferred: int} */
    public function refreshDue(int $limit, int $deadlineSeconds): array
    {
        $limit = max(1, min(50, $limit));
        $deadline = microtime(true) + max(5, min(45, $deadlineSeconds));
        $candidates = KnowledgeDocument::query()
            ->with('source')
            ->where('status', 'active')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now())
            ->whereHas('source', fn ($query) => $query->where('kind', 'web_page'))
            ->orderBy('valid_until')
            ->orderBy('id')
            ->limit($limit * 5)
            ->get();

        $run = WebResearchRun::query()->create([
            'public_id' => (string) Str::uuid(),
            'query' => 'scheduled:web-refresh',
            'query_hash' => hash('sha256', 'scheduled:web-refresh'),
            'status' => 'running',
            'requested_depth' => $limit,
            'started_at' => now(),
        ]);
        $stats = ['processed' => 0, 'updated' => 0, 'failed' => 0, 'deferred' => 0];
        $hosts = [];

        foreach ($candidates as $document) {
            if ($stats['processed'] >= $limit || microtime(true) >= $deadline) {
                $stats['deferred']++;
                continue;
            }
            $source = $document->source;
            $nextAttempt = data_get($source->meta_json, 'refresh_next_attempt_at');
            if (is_string($nextAttempt) && CarbonImmutable::parse($nextAttempt)->isFuture()) {
                $stats['deferred']++;
                continue;
            }
            $url = (string) $source->canonical_uri;
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || isset($hosts[$host])) {
                $stats['deferred']++;
                continue;
            }
            $hosts[$host] = true;
            $stats['processed']++;

            try {
                $fetch = $this->fetcher->fetch($url);
                if (! ($fetch['ok'] ?? false)) {
                    throw new \RuntimeException((string) ($fetch['error'] ?? 'fetch_failed'));
                }
                $page = $this->extractor->extract((string) $fetch['html'], (string) $fetch['url']);
                $publishedAt = filled($page['published_at']) ? CarbonImmutable::parse($page['published_at']) : null;
                $policy = $this->policy->assess((string) $page['canonical_url'], $publishedAt);
                $this->ingestor->ingest($run, [
                    'provider' => 'refresh', 'rank' => $stats['processed'],
                    'title' => $page['title'], 'url' => $url, 'snippet' => '',
                ], $fetch, $page, $policy);
                $meta = is_array($source->meta_json) ? $source->meta_json : [];
                unset($meta['refresh_next_attempt_at'], $meta['refresh_error']);
                $source->update(['meta_json' => $meta]);
                $stats['updated']++;
            } catch (\Throwable $exception) {
                $meta = is_array($source->meta_json) ? $source->meta_json : [];
                $meta['refresh_next_attempt_at'] = now()->addHours(6)->toIso8601String();
                $meta['refresh_error'] = Str::limit($exception->getMessage(), 80, '');
                $source->update(['meta_json' => $meta]);
                $stats['failed']++;
            }
        }

        $run->update([
            'status' => 'completed',
            'result_count' => $stats['updated'],
            'completed_at' => now(),
            'checkpoint_json' => $stats,
        ]);

        return $stats;
    }
}
