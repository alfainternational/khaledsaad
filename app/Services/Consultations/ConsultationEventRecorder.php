<?php

namespace App\Services\Consultations;

use App\Models\ConsultationEvent;
use App\Models\ConsultationSession;

class ConsultationEventRecorder
{
    /** @var array<int,string> */
    private const EVENTS = [
        'session_started', 'session_resumed', 'answer_saved', 'answer_revised',
        'review_opened', 'conflict_detected', 'conflict_resolved', 'analysis_queued',
        'analysis_completed', 'analysis_failed', 'session_deleted',
    ];

    /** @var array<int,string> */
    private const METADATA = [
        'question_key', 'module_key', 'source', 'depth', 'status', 'conflict_key',
        'answer_type', 'is_unknown', 'is_skipped', 'questions_answered', 'report_uuid',
    ];

    /** @param array<string,mixed> $metadata */
    public function record(ConsultationSession $session, string $name, array $metadata = []): ConsultationEvent
    {
        if (! in_array($name, self::EVENTS, true)) {
            throw new \InvalidArgumentException('اسم حدث الاستشارة غير مسموح.');
        }

        $safe = collect($metadata)
            ->only(self::METADATA)
            ->filter(fn ($value) => is_null($value) || is_bool($value) || is_int($value) || is_float($value) || is_string($value))
            ->all();

        return $session->events()->create([
            'name' => $name,
            'metadata' => $safe,
            'occurred_at' => now(),
        ]);
    }
}
