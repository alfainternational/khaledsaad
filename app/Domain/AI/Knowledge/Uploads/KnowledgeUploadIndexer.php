<?php

namespace App\Domain\AI\Knowledge\Uploads;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KnowledgeUploadIndexer
{
    public function __construct(
        private readonly TextKnowledgeExtractor $extractor,
        private readonly StructuredKnowledgeRepository $repository,
    ) {}

    public function index(KnowledgeUpload $upload): KnowledgeUpload
    {
        try {
            $project = $upload->project()->with('workspace')->firstOrFail();
            $workspace = $project->workspace;
            $scope = KnowledgeScope::forProject(
                (int) $workspace->account_id,
                (int) $workspace->id,
                (int) $project->id,
            );
            $path = Storage::disk($upload->disk)->path($upload->path);
            $extracted = $this->extractor->extract($path, $upload->mime_type, $upload->original_name);
            $document = $this->repository->storeDocument(
                scope: $scope,
                kind: 'uploaded_file',
                canonicalUri: 'upload://'.$upload->public_id,
                title: $upload->original_name,
                content: $extracted->content,
                chunks: $extracted->chunks,
                trustScore: 85,
            );

            $upload->update([
                'knowledge_source_id' => $document->knowledge_source_id,
                'status' => 'indexed',
                'error_code' => null,
                'extraction_meta_json' => $extracted->metadata + ['language' => $extracted->language],
            ]);

            return $upload->fresh();
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'error_code' => $exception instanceof KnowledgeExtractionException
                    ? $exception->machineCode
                    : 'indexing_failed',
            ]);

            throw $exception;
        }
    }
}
