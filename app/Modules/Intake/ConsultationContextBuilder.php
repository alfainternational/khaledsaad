<?php

namespace App\Modules\Intake;

use App\Models\ConsultationSession;

class ConsultationContextBuilder
{
    /** @return array<string,mixed> */
    public function build(ConsultationSession $session): array
    {
        $session->loadMissing([
            'answers.questionVersion.definition', 'moduleStates.module', 'inferences', 'conflicts', 'evidence',
        ]);

        return [
            'uuid' => $session->uuid,
            'depth' => $session->depth,
            'status' => $session->status,
            'scope' => $session->moduleStates->map(fn ($state) => [
                'key' => $state->module->key,
                'name' => $state->module->name,
                'state' => $state->state,
                'reason' => $state->reason,
                'confidence' => $state->confidence,
            ])->values()->all(),
            'answers' => $session->answers->map(fn ($answer) => [
                'key' => $answer->questionVersion->definition->internal_variable,
                'question' => $answer->questionVersion->user_text,
                'value' => data_get($answer->value_json, 'value'),
                'source' => $answer->source,
                'confidence' => $answer->confidence,
                'period' => $answer->period,
                'is_unknown' => $answer->is_unknown,
                'is_skipped' => $answer->is_skipped,
            ])->values()->all(),
            'inferences' => $session->inferences->map(fn ($inference) => [
                'key' => $inference->key,
                'type' => $inference->type,
                'statement' => $inference->statement,
                'confidence' => $inference->confidence,
                'status' => $inference->status,
                'evidence_ids' => $inference->evidence_ids ?? [],
                'opposing_evidence_ids' => $inference->opposing_evidence_ids ?? [],
            ])->values()->all(),
            'conflicts' => $session->conflicts->map(fn ($conflict) => [
                'key' => $conflict->key,
                'severity' => $conflict->severity,
                'message' => $conflict->message,
                'subject' => $conflict->subject ?? [],
                'status' => $conflict->status,
                'resolution' => $conflict->resolution,
                'resolved_at' => $conflict->resolved_at?->toIso8601String(),
            ])->values()->all(),
            'evidence' => $session->evidence->map(fn ($evidence) => [
                'id' => $evidence->id,
                'name' => $evidence->source_label,
                'type' => $evidence->type,
                'mime_type' => $evidence->mime_type,
                'size_bytes' => $evidence->size_bytes,
                'confidence' => $evidence->confidence,
                'extraction_status' => $evidence->extraction_status,
                'sha256' => $evidence->sha256,
                'text' => $evidence->extraction_status === 'completed'
                    ? mb_substr((string) $evidence->extracted_text, 0, 6000)
                    : null,
                'review_required' => (bool) data_get($evidence->metadata, 'review_required', false),
            ])->values()->all(),
        ];
    }
}
