<?php

namespace App\Domain\AI\Web;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Web\Models\WebResearchResult;
use App\Domain\AI\Web\Models\WebResearchRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WebKnowledgeIngestor
{
    public function __construct(
        private readonly StructuredKnowledgeRepository $knowledge,
        private readonly WebSearchResultNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $searchResult
     * @param  array<string, mixed>  $fetch
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $policy
     */
    public function ingest(
        WebResearchRun $run,
        array $searchResult,
        array $fetch,
        array $page,
        array $policy,
    ): WebResearchResult {
        $canonicalUrl = $this->normalizer->normalizeUrl((string) ($page['canonical_url'] ?? $fetch['url'] ?? ''));
        if ($canonicalUrl === null) {
            throw new \InvalidArgumentException('web_page_has_invalid_canonical_url');
        }

        $fetchedAt = now();
        $citation = [
            'url' => $canonicalUrl,
            'title' => (string) ($page['title'] ?: ($searchResult['title'] ?? '')),
            'fetched_at' => $fetchedAt->toIso8601String(),
            'published_at' => $page['published_at'] ?? null,
        ];
        $chunks = $this->chunks((string) $page['text'], $citation);

        return DB::transaction(function () use (
            $run, $searchResult, $fetch, $page, $policy, $canonicalUrl, $fetchedAt, $citation, $chunks
        ): WebResearchResult {
            $document = $this->knowledge->storeDocument(
                KnowledgeScope::global(),
                'web_page',
                $canonicalUrl,
                $citation['title'],
                (string) $page['text'],
                $chunks,
                (int) $policy['trust_score'],
            );

            $sourceMeta = is_array($document->source->meta_json) ? $document->source->meta_json : [];
            $document->source->update([
                'trust_score' => (int) $policy['trust_score'],
                'meta_json' => array_merge($sourceMeta, [
                    'trust_tier' => (string) $policy['trust_tier'],
                    'last_fetched_at' => $fetchedAt->toIso8601String(),
                ]),
            ]);
            $documentMeta = is_array($document->meta_json) ? $document->meta_json : [];
            $document->update([
                'language' => (string) ($page['language'] ?? 'und'),
                'valid_from' => $page['published_at'] ?? $fetchedAt,
                'valid_until' => $policy['valid_until'] ?? null,
                'meta_json' => array_merge($documentMeta, [
                    'citation' => $citation,
                    'freshness_status' => (string) $policy['freshness_status'],
                    'verification_status' => 'unverified',
                ]),
            ]);

            return WebResearchResult::query()->updateOrCreate(
                [
                    'web_research_run_id' => $run->id,
                    'normalized_url_hash' => hash('sha256', $canonicalUrl),
                ],
                [
                    'knowledge_source_id' => $document->knowledge_source_id,
                    'knowledge_document_id' => $document->id,
                    'provider' => (string) ($searchResult['provider'] ?? 'unknown'),
                    'rank' => max(1, (int) ($searchResult['rank'] ?? 1)),
                    'title' => $citation['title'],
                    'original_url' => (string) ($searchResult['url'] ?? $canonicalUrl),
                    'normalized_url' => $canonicalUrl,
                    'domain' => strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST)),
                    'snippet' => (string) ($searchResult['snippet'] ?? ''),
                    'content_hash' => (string) $page['content_hash'],
                    'fetch_status' => 'fetched',
                    'http_status' => (int) ($fetch['status'] ?? 200),
                    'trust_tier' => (string) $policy['trust_tier'],
                    'trust_score' => (int) $policy['trust_score'],
                    'freshness_status' => (string) $policy['freshness_status'],
                    'verification_status' => 'unverified',
                    'published_at' => $page['published_at'] ?? null,
                    'fetched_at' => $fetchedAt,
                    'valid_until' => $policy['valid_until'] ?? null,
                    'meta_json' => ['citation' => $citation],
                ],
            )->load(['knowledgeSource', 'knowledgeDocument']);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function chunks(string $text, array $citation): array
    {
        $size = max(500, (int) config('services.knowledge.upload_chunk_chars', 3500));
        $chunks = [];
        for ($offset = 0, $length = mb_strlen($text); $offset < $length; $offset += $size) {
            $chunks[] = [
                'content' => mb_substr($text, $offset, $size),
                'heading' => $citation['title'],
                'locator' => ['url' => $citation['url'], 'title' => $citation['title'], 'char_start' => $offset],
            ];
        }

        return $chunks;
    }
}
