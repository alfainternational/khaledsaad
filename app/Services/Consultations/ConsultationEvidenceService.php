<?php

namespace App\Services\Consultations;

use App\Models\ConsultationEvidence;
use App\Models\ConsultationSession;
use App\Models\ProjectAnswer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConsultationEvidenceService
{
    public function store(ConsultationSession $session, UploadedFile $file): ConsultationEvidence
    {
        $consent = ProjectAnswer::where('project_id', $session->project_id)->where('field_key', 'source_consent')->first();
        $value = data_get($consent?->value_json, 'value');
        if (! in_array($value, ['نعم', 'نعم بعد المراجعة'], true)) {
            throw ValidationException::withMessages(['file' => 'يلزم منح الإذن بتحليل الملفات أولًا.']);
        }
        $path = $file->store("consultations/{$session->uuid}", 'local');

        return $session->evidence()->create([
            'type' => 'uploaded_file',
            'source_label' => $file->getClientOriginalName(),
            'source_locator' => $path,
            'confidence' => 'high',
            'metadata' => ['mime' => $file->getMimeType(), 'size' => $file->getSize(), 'review_required' => $value === 'نعم بعد المراجعة'],
            'observed_at' => now(),
        ]);
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
