<?php

namespace App\Modules\Intake;

use App\Models\ConsultationEvidence;
use App\Models\ConsultationSession;
use App\Models\ProjectAnswer;
use App\Services\Tools\AttachmentExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConsultationEvidenceService
{
    public function __construct(private readonly AttachmentExtractor $extractor) {}

    public function store(ConsultationSession $session, UploadedFile $file): ConsultationEvidence
    {
        $consent = ProjectAnswer::where('project_id', $session->project_id)->where('field_key', 'source_consent')->first();
        $value = data_get($consent?->value_json, 'value');
        if (! in_array($value, ['نعم', 'نعم بعد المراجعة'], true)) {
            throw ValidationException::withMessages(['file' => 'يلزم منح الإذن بتحليل الملفات أولًا.']);
        }
        $path = $file->store("consultations/{$session->uuid}", 'local');

        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $evidence = $session->evidence()->create([
            'type' => 'uploaded_file',
            'source_label' => $file->getClientOriginalName(),
            'source_locator' => $path,
            'disk' => 'local',
            'mime_type' => $mimeType,
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'confidence' => 'high',
            'metadata' => ['mime' => $mimeType, 'size' => $file->getSize(), 'review_required' => $value === 'نعم بعد المراجعة'],
            'observed_at' => now(),
        ]);

        $evidence->forceFill($this->extractor->extractStoredFile(
            $evidence->disk,
            $evidence->source_locator,
            $evidence->mime_type,
            $evidence->source_label,
        ))->save();

        return $evidence->refresh();
    }

    public function delete(ConsultationSession $session, ConsultationEvidence $evidence): void
    {
        abort_unless($evidence->consultation_session_id === $session->id, 404);
        if ($evidence->source_locator) {
            Storage::disk('local')->delete($evidence->source_locator);
        }
        $evidence->delete();
    }
}
