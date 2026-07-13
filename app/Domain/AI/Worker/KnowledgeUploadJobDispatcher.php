<?php

namespace App\Domain\AI\Worker;

use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use Illuminate\Support\Str;

class KnowledgeUploadJobDispatcher
{
    public function dispatch(KnowledgeUpload $upload): IntelligenceJob
    {
        $type = str_starts_with($upload->mime_type, 'image/') ? 'ocr' : 'document_extract';
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $upload->account_id,
            'workspace_id' => $upload->workspace_id,
            'project_id' => $upload->project_id,
            'type' => $type,
            'status' => 'queued',
            'payload_json' => [
                'upload_public_id' => $upload->public_id,
                'mime_type' => $upload->mime_type,
                'original_name' => $upload->original_name,
                'expected_sha256' => $upload->sha256,
                'extraction_contract' => DocumentExtractionContract::definition(),
            ],
            'input_hash' => $upload->sha256,
            'available_at' => now(),
            'timeout_seconds' => $type === 'ocr' ? 600 : 300,
            'max_attempts' => 3,
        ]);
        $upload->update([
            'status' => 'needs_worker',
            'error_code' => null,
            'extraction_meta_json' => ['job_public_id' => $job->public_id, 'required_capability' => $type],
        ]);

        return $job;
    }
}
