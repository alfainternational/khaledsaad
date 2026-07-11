<?php

namespace App\Domain\AI\Worker;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;

class WorkerResultApplier
{
    public function __construct(private readonly StructuredKnowledgeRepository $repository) {}

    /** @param array<string, mixed> $result */
    public function apply(IntelligenceJob $job, IntelligenceWorker $worker, array $result): void
    {
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
