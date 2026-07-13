<?php

namespace App\Domain\AI\Chat;

use App\Domain\AI\Chat\Models\AiChatMessage;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\WorkerProtocolException;

class ChatMessageLifecycle
{
    public function __construct(private readonly ChatAnswerExtractor $answers) {}

    /** @param array<string, mixed> $result */
    public function complete(IntelligenceJob $job, array $result): void
    {
        $message = $this->target($job);
        if ($message->status === 'completed') {
            return;
        }

        try {
            $answer = $this->answers->extract($result);
        } catch (\InvalidArgumentException $exception) {
            throw new WorkerProtocolException('WORKER_RESULT_CHAT_INVALID', 422, $exception->getMessage());
        }

        $message->update([
            'status' => 'completed',
            'content' => $answer,
            'error_code' => null,
            'error_message' => null,
            'meta_json' => array_filter([
                'model_name' => $result['_model_name'] ?? null,
                'model_version' => $result['_model_version'] ?? null,
            ], static fn ($value): bool => is_string($value) && $value !== ''),
            'completed_at' => now(),
        ]);
    }

    public function fail(IntelligenceJob $job): void
    {
        $message = $this->target($job);
        if (in_array($message->status, ['completed', 'failed'], true)) {
            return;
        }

        $message->update([
            'status' => 'failed',
            'error_code' => 'AI_RESPONSE_FAILED',
            'error_message' => 'تعذر إكمال الرد الآن. يمكنك إعادة المحاولة دون فقدان المحادثة.',
            'completed_at' => now(),
        ]);
    }

    private function target(IntelligenceJob $job): AiChatMessage
    {
        $publicId = $job->payload_json['chat_message_public_id'] ?? null;
        $message = is_string($publicId)
            ? AiChatMessage::query()
                ->where('public_id', $publicId)
                ->with('conversation')
                ->lockForUpdate()
                ->first()
            : null;
        $conversation = $message?->conversation;

        if (
            ! $message
            || ! $conversation
            || $message->role !== 'assistant'
            || $message->intelligence_job_id !== $job->id
            || $conversation->account_id !== $job->account_id
            || $conversation->workspace_id !== $job->workspace_id
            || $conversation->project_id !== $job->project_id
        ) {
            throw new WorkerProtocolException('WORKER_RESULT_TARGET_INVALID', 422, 'The chat result target does not match its tenant.');
        }

        return $message;
    }
}
