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
        $job = IntelligenceJob::query()
            ->where('account_id', $upload->account_id)
            ->where('workspace_id', $upload->workspace_id)
            ->where('project_id', $upload->project_id)
            ->where('type', $type)
            ->whereIn('status', ['queued', 'leased'])
            ->where('payload_json->upload_public_id', $upload->public_id)
            ->latest('id')
            ->first();

        if ($job) {
            $this->markForWorker($upload, $job, $type);

            return $job;
        }

        $payload = [
            'upload_public_id' => $upload->public_id,
            'mime_type' => $upload->mime_type,
            'original_name' => $upload->original_name,
            'expected_sha256' => $upload->sha256,
        ];
        if ((bool) config('services.knowledge.structured_extraction', false)) {
            $payload['extraction_contract'] = DocumentExtractionContract::definition();
        }

        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $upload->account_id,
            'workspace_id' => $upload->workspace_id,
            'project_id' => $upload->project_id,
            'type' => $type,
            'status' => 'queued',
            'payload_json' => $payload,
            'input_hash' => $upload->sha256,
            'available_at' => now(),
            'timeout_seconds' => $type === 'ocr' ? 600 : 300,
            'max_attempts' => 3,
        ]);
        $this->markForWorker($upload, $job, $type);

        return $job;
    }

    private function markForWorker(KnowledgeUpload $upload, IntelligenceJob $job, string $type): void
    {
        $upload->update([
            'status' => 'needs_worker',
            'error_code' => null,
            'extraction_meta_json' => ['job_public_id' => $job->public_id, 'required_capability' => $type],
        ]);
    }
}
