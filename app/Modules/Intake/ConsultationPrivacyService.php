<?php

namespace App\Modules\Intake;

use App\Models\ConsultationSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConsultationPrivacyService
{
    /** @return array<string,mixed> */
    public function export(ConsultationSession $session): array
    {
        $session->load(['project', 'blueprintVersion.blueprint', 'answers.questionVersion.definition', 'evidence', 'conflicts', 'inferences', 'moduleStates.module', 'events']);

        return [
            'exported_at' => now()->toIso8601String(),
            'session' => [
                'uuid' => $session->uuid,
                'project' => $session->project->only(['slug', 'name']),
                'blueprint' => $session->blueprintVersion->blueprint->key,
                'blueprint_version' => $session->blueprintVersion->version,
                'status' => $session->status,
                'depth' => $session->depth,
                'created_at' => $session->created_at?->toIso8601String(),
                'completed_at' => $session->completed_at?->toIso8601String(),
            ],
            'answers' => $session->answers->map(fn ($answer) => [
                'question_key' => $answer->questionVersion->definition->key,
                'question' => $answer->questionVersion->user_text,
                'value' => data_get($answer->value_json, 'value'),
                'source' => $answer->source,
                'confidence' => $answer->confidence,
                'unknown' => $answer->is_unknown,
                'skipped' => $answer->is_skipped,
            ])->values()->all(),
            'scope' => $session->moduleStates->map(fn ($state) => [
                'module' => $state->module->key,
                'state' => $state->state,
                'reason' => $state->reason,
            ])->values()->all(),
            'conflicts' => $session->conflicts->toArray(),
            'inferences' => $session->inferences->toArray(),
            'evidence' => $session->evidence->map(fn ($item) => [
                'type' => $item->type,
                'name' => $item->source_label,
                'confidence' => $item->confidence,
                'metadata' => $item->metadata,
                'observed_at' => $item->observed_at?->toIso8601String(),
            ])->values()->all(),
            'events' => $session->events->map(fn ($event) => [
                'name' => $event->name,
                'metadata' => $event->metadata,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function delete(ConsultationSession $session): void
    {
        DB::transaction(function () use ($session): void {
            foreach ($session->evidence()->pluck('source_locator')->filter() as $path) {
                Storage::disk('local')->delete($path);
            }
            $session->agencyReport()->update(['consultation_session_id' => null]);
            $session->delete();
        });
    }
}
