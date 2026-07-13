<?php

namespace App\Domain\AI\Worker;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeChunk;
use App\Domain\AI\Knowledge\Models\KnowledgeEmbedding;
use App\Domain\AI\Knowledge\Models\KnowledgeQueryEmbedding;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Knowledge\VectorMath;
use App\Domain\AI\Web\Models\WebResearchRun;
use App\Domain\AI\Web\WebEvidenceVerifier;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;

class WorkerResultApplier
{
    public function __construct(
        private readonly StructuredKnowledgeRepository $repository,
        private readonly VectorMath $vectorMath,
        private readonly WebEvidenceVerifier $webEvidenceVerifier,
    ) {}

    /** @param array<string, mixed> $result */
    public function apply(IntelligenceJob $job, IntelligenceWorker $worker, array $result): void
    {
        if ($job->type === 'embeddings') {
            $this->applyEmbeddings($job, $result);

            return;
        }

        if ($job->type === 'local_llm' && ($job->payload_json['purpose'] ?? null) === 'web_claim_verification') {
            $this->applyWebClaims($job, $result);

            return;
        }

        if (! in_array($job->type, ['ocr', 'document_extract'], true)) {
            return;
        }

        $uploadId = $job->payload_json['upload_public_id'] ?? null;
        $upload = is_string($uploadId)
            ? KnowledgeUpload::query()
                ->where('public_id', $uploadId)
                ->where('account_id', $job->account_id)
                ->where('workspace_id', $job->workspace_id)
                ->where('project_id', $job->project_id)
                ->with('project.workspace')
                ->first()
            : null;
        if (! $upload || ! $upload->project?->workspace) {
            throw new WorkerProtocolException('WORKER_RESULT_TARGET_INVALID', 422, 'The result target does not match the job tenant.');
        }

        $text = $result['text'] ?? null;
        if (
            ! is_string($text)
            || trim($text) === ''
            || str_contains($text, "\0")
            || ! mb_check_encoding($text, 'UTF-8')
            || mb_strlen($text) > (int) config('services.knowledge.upload_max_text_chars', 350000)
        ) {
            throw new WorkerProtocolException('WORKER_RESULT_TEXT_INVALID', 422, 'The extracted text is invalid or exceeds its limit.');
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $chunks = $this->chunks($result['chunks'] ?? null, $text);
        $scope = KnowledgeScope::forProject($upload->account_id, $upload->workspace_id, $upload->project_id);
        $document = $this->repository->storeDocument(
            $scope,
            'uploaded_file',
            'upload://'.$upload->public_id,
            $upload->original_name,
            $text,
            $chunks,
            80,
        );
        $upload->update([
            'knowledge_source_id' => $document->knowledge_source_id,
            'status' => 'indexed',
            'error_code' => null,
            'extraction_meta_json' => [
                'format' => $job->type,
                'worker_id' => $worker->public_id,
                'model_name' => $result['model_name'] ?? null,
                'language' => $result['language'] ?? null,
                'chunk_count' => count($chunks),
            ],
        ]);
    }

    /** @param array<string, mixed> $result */
    private function applyWebClaims(IntelligenceJob $job, array $result): void
    {
        $runPublicId = $job->payload_json['run_public_id'] ?? null;
        $claims = $result['claims'] ?? null;
        if (! is_string($runPublicId) || ! is_array($claims) || ! array_is_list($claims) || count($claims) > 50) {
            throw new WorkerProtocolException('WORKER_RESULT_WEB_CLAIMS_INVALID', 422, 'The web claim result contract is invalid.');
        }

        $run = WebResearchRun::query()->where('public_id', $runPublicId)
            ->with(['results.knowledgeDocument'])->first();
        if (! $run) {
            throw new WorkerProtocolException('WORKER_RESULT_TARGET_INVALID', 422, 'The web research run no longer exists.');
        }
        $resultsByUrl = $run->results->keyBy('normalized_url');
        $verifierInput = [];
        $resultIdsByClaim = [];

        foreach ($claims as $claim) {
            $key = is_array($claim) ? trim((string) ($claim['key'] ?? '')) : '';
            $evidence = is_array($claim) ? ($claim['evidence'] ?? null) : null;
            if ($key === '' || mb_strlen($key) > 120 || ! is_array($evidence) || ! array_is_list($evidence) || count($evidence) > 10) {
                throw new WorkerProtocolException('WORKER_RESULT_WEB_CLAIMS_INVALID', 422, 'A web claim entry is invalid.');
            }

            foreach ($evidence as $entry) {
                $url = is_array($entry) ? trim((string) ($entry['url'] ?? '')) : '';
                $value = is_array($entry) ? trim((string) ($entry['value'] ?? '')) : '';
                $quote = is_array($entry) ? $this->normalizeQuote((string) ($entry['quote'] ?? '')) : '';
                $webResult = $resultsByUrl->get($url);
                $documentText = $this->normalizeQuote((string) $webResult?->knowledgeDocument?->content);
                if (! $webResult || $value === '' || mb_strlen($value) > 500 || $quote === '' || mb_strlen($quote) > 1000
                    || ! str_contains($documentText, $quote)) {
                    throw new WorkerProtocolException('WORKER_RESULT_WEB_CLAIMS_INVALID', 422, 'A web claim quote is not grounded in its source.');
                }

                $verifierInput[] = [
                    'claim_key' => $key,
                    'claim_value' => $value,
                    'domain' => $webResult->domain,
                    'trust_score' => $webResult->trust_score,
                    'freshness_status' => $webResult->freshness_status,
                ];
                $resultIdsByClaim[$key][$webResult->id] = true;
            }
        }

        $findings = $this->webEvidenceVerifier->verify($verifierInput);
        $statusByResult = [];
        foreach ($findings as $finding) {
            foreach (array_keys($resultIdsByClaim[$finding->claimKey] ?? []) as $resultId) {
                $current = $statusByResult[$resultId] ?? 'unverified';
                $statusByResult[$resultId] = $finding->status === 'conflict'
                    ? 'conflict'
                    : ($finding->status === 'verified' && $current !== 'conflict' ? 'verified' : $current);
            }
        }

        foreach ($run->results as $webResult) {
            $status = $statusByResult[$webResult->id] ?? 'unverified';
            $meta = is_array($webResult->meta_json) ? $webResult->meta_json : [];
            $webResult->update([
                'verification_status' => $status,
                'meta_json' => array_merge($meta, [
                    'claim_verification' => array_map(
                        static fn ($finding): array => $finding->toArray(),
                        $findings,
                    ),
                    'verified_by_worker_job' => $job->public_id,
                ]),
            ]);
        }
        $run->update([
            'verified_count' => $run->results()->where('verification_status', 'verified')->count(),
            'conflict_count' => $run->results()->where('verification_status', 'conflict')->count(),
        ]);
    }

    private function normalizeQuote(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** @param array<string, mixed> $result */
    private function applyEmbeddings(IntelligenceJob $job, array $result): void
    {
        $payload = $job->payload_json;
        $expectedModel = $payload['model_name'] ?? null;
        $expectedVersion = $payload['model_version'] ?? null;
        $items = $payload['items'] ?? null;
        $vectors = $result['vectors'] ?? null;
        if (
            ! is_string($expectedModel) || $expectedModel === ''
            || ! is_string($expectedVersion) || $expectedVersion === ''
            || ($result['model_name'] ?? null) !== $expectedModel
            || ($result['model_version'] ?? null) !== $expectedVersion
            || ! is_array($items) || ! array_is_list($items)
            || ! is_array($vectors) || ! array_is_list($vectors)
            || $items === [] || count($items) !== count($vectors)
            || count($items) > (int) config('services.knowledge.embedding_batch_size', 16)
        ) {
            throw new WorkerProtocolException('WORKER_RESULT_EMBEDDINGS_INVALID', 422, 'The embedding result does not match its job contract.');
        }

        if (($payload['target'] ?? 'chunks') === 'query') {
            $this->applyQueryEmbeddings($job, $items, $vectors, $expectedModel, $expectedVersion);

            return;
        }

        $expected = collect($items)->keyBy(fn ($item) => is_array($item) ? (string) ($item['chunk_id'] ?? '') : '');
        if ($expected->count() !== count($items) || $expected->has('')) {
            throw new WorkerProtocolException('WORKER_RESULT_EMBEDDINGS_INVALID', 422, 'The embedding job contains invalid or duplicate items.');
        }

        $seen = [];
        foreach ($vectors as $entry) {
            $chunkId = is_array($entry) ? filter_var($entry['chunk_id'] ?? null, FILTER_VALIDATE_INT) : false;
            $contentHash = is_array($entry) ? ($entry['content_hash'] ?? null) : null;
            $vector = is_array($entry) ? ($entry['vector'] ?? null) : null;
            $contract = $chunkId !== false ? $expected->get((string) $chunkId) : null;
            if (
                ! is_array($contract) || isset($seen[$chunkId]) || ! is_string($contentHash)
                || ! hash_equals((string) ($contract['content_hash'] ?? ''), $contentHash)
                || ! is_array($vector)
            ) {
                throw new WorkerProtocolException('WORKER_RESULT_EMBEDDINGS_INVALID', 422, 'An embedding item does not match its job contract.');
            }

            $chunk = KnowledgeChunk::query()->with('document.source')->find($chunkId);
            $source = $chunk?->document?->source;
            if (
                ! $chunk || ! $source || $chunk->document->status !== 'active'
                || $source->account_id !== $job->account_id
                || $source->workspace_id !== $job->workspace_id
                || $source->project_id !== $job->project_id
                || ! hash_equals($contentHash, hash('sha256', $chunk->content))
            ) {
                throw new WorkerProtocolException('WORKER_RESULT_TARGET_INVALID', 422, 'An embedding target is stale or outside the job tenant.');
            }

            try {
                $normalized = $this->vectorMath->normalize($vector);
            } catch (\InvalidArgumentException $exception) {
                throw new WorkerProtocolException('WORKER_RESULT_EMBEDDINGS_INVALID', 422, $exception->getMessage());
            }

            KnowledgeEmbedding::query()->updateOrCreate(
                ['knowledge_chunk_id' => $chunk->id, 'model_name' => $expectedModel, 'model_version' => $expectedVersion],
                ['dimensions' => count($normalized), 'content_hash' => $contentHash, 'vector_json' => $normalized, 'status' => 'active'],
            );
            $seen[$chunkId] = true;
        }
    }

    /** @param list<mixed> $items
     * @param  list<mixed>  $vectors
     */
    private function applyQueryEmbeddings(IntelligenceJob $job, array $items, array $vectors, string $model, string $version): void
    {
        $scope = match (true) {
            $job->account_id === null && $job->workspace_id === null && $job->project_id === null => KnowledgeScope::global(),
            $job->account_id !== null && $job->workspace_id !== null && $job->project_id === null => KnowledgeScope::forWorkspace($job->account_id, $job->workspace_id),
            $job->account_id !== null && $job->workspace_id !== null && $job->project_id !== null => KnowledgeScope::forProject($job->account_id, $job->workspace_id, $job->project_id),
            default => throw new WorkerProtocolException('WORKER_RESULT_TARGET_INVALID', 422, 'The query embedding tenant is invalid.'),
        };
        $seen = [];
        foreach ($vectors as $index => $entry) {
            $contract = $items[$index] ?? null;
            if (
                ! is_array($contract) || ! is_array($entry)
                || ! is_string($contract['scope_key'] ?? null) || ! hash_equals($scope->key(), $contract['scope_key'])
                || ! is_string($contract['query_hash'] ?? null) || strlen($contract['query_hash']) !== 64
                || ($entry['scope_key'] ?? null) !== $contract['scope_key']
                || ($entry['query_hash'] ?? null) !== $contract['query_hash']
                || ! is_array($entry['vector'] ?? null)
                || isset($seen[$contract['query_hash']])
            ) {
                throw new WorkerProtocolException('WORKER_RESULT_EMBEDDINGS_INVALID', 422, 'A query embedding does not match its job contract.');
            }
            try {
                $normalized = $this->vectorMath->normalize($entry['vector']);
            } catch (\InvalidArgumentException $exception) {
                throw new WorkerProtocolException('WORKER_RESULT_EMBEDDINGS_INVALID', 422, $exception->getMessage());
            }
            KnowledgeQueryEmbedding::query()->updateOrCreate(
                ['scope_key' => $scope->key(), 'query_hash' => $contract['query_hash'], 'model_name' => $model, 'model_version' => $version],
                ['dimensions' => count($normalized), 'vector_json' => $normalized, 'expires_at' => now()->addDays((int) config('services.knowledge.query_embedding_ttl_days', 7))],
            );
            $seen[$contract['query_hash']] = true;
        }
    }

    /** @return list<array{heading: string|null, content: string, locator: array<string, mixed>}> */
    private function chunks(mixed $provided, string $text): array
    {
        if (is_array($provided) && $provided !== []) {
            if (count($provided) > 100) {
                throw new WorkerProtocolException('WORKER_RESULT_CHUNKS_INVALID', 422, 'The result contains too many chunks.');
            }

            return collect($provided)->values()->map(function ($chunk, int $position): array {
                if (! is_array($chunk) || ! is_string($chunk['content'] ?? null) || trim($chunk['content']) === '') {
                    throw new WorkerProtocolException('WORKER_RESULT_CHUNKS_INVALID', 422, 'A result chunk is invalid.');
                }

                return [
                    'heading' => is_string($chunk['heading'] ?? null) ? mb_substr($chunk['heading'], 0, 255) : null,
                    'content' => trim($chunk['content']),
                    'locator' => is_array($chunk['locator'] ?? null) ? $chunk['locator'] : ['position' => $position],
                ];
            })->all();
        }

        $parts = mb_str_split($text, (int) config('services.knowledge.upload_chunk_chars', 3500));

        return collect($parts)->map(fn (string $part, int $position): array => [
            'heading' => null,
            'content' => trim($part),
            'locator' => ['character_start' => $position * 3500, 'character_end' => ($position * 3500) + mb_strlen($part)],
        ])->filter(fn (array $chunk): bool => $chunk['content'] !== '')->values()->all();
    }
}
