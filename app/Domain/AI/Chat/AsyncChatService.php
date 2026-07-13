<?php

namespace App\Domain\AI\Chat;

use App\Domain\AI\Chat\Models\AiChatConversation;
use App\Domain\AI\Chat\Models\AiChatMessage;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Api\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AsyncChatService
{
    public function __construct(private readonly ChatPromptBuilder $prompts) {}

    public function createConversation(
        User $user,
        Workspace $workspace,
        ?Project $project = null,
        string $toolKey = 'general',
    ): AiChatConversation {
        $this->assertMembership($user, $workspace);
        if ($project && $project->workspace_id !== $workspace->id) {
            throw new ApiException('المشروع المحدد لا ينتمي إلى مساحة العمل الحالية.', 'CHAT_PROJECT_SCOPE_INVALID', 422);
        }

        return AiChatConversation::query()->create([
            'account_id' => $workspace->account_id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'title' => 'محادثة جديدة',
            'tool_key' => mb_substr(trim($toolKey) ?: 'general', 0, 100),
            'last_message_at' => now(),
        ]);
    }

    /** @return array{user_message: AiChatMessage, assistant_message: AiChatMessage, job: IntelligenceJob} */
    public function send(
        User $user,
        Workspace $workspace,
        AiChatConversation $conversation,
        string $content,
        string $clientRequestId,
    ): array {
        $content = trim($content);
        $clientRequestId = trim($clientRequestId);
        if ($content === '' || mb_strlen($content) > 5000 || $clientRequestId === '' || mb_strlen($clientRequestId) > 100) {
            throw new ApiException('بيانات رسالة المحادثة غير صالحة.', 'CHAT_MESSAGE_INVALID', 422);
        }
        if (! $this->hasOnlineWorker()) {
            throw new ApiException('المساعد المحلي غير متاح الآن. حاول مرة أخرى بعد قليل.', 'AI_WORKER_UNAVAILABLE', 503);
        }

        return DB::transaction(function () use ($user, $workspace, $conversation, $content, $clientRequestId): array {
            $locked = AiChatConversation::query()
                ->whereKey($conversation->id)
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if (! $locked) {
                throw new ApiException('المحادثة المطلوبة غير موجودة.', 'NOT_FOUND', 404);
            }

            $existing = $locked->messages()->where('client_request_id', $clientRequestId)->first();
            if ($existing) {
                $assistant = $locked->messages()
                    ->where('id', '>', $existing->id)
                    ->where('role', 'assistant')
                    ->oldest('id')
                    ->firstOrFail();

                return [
                    'user_message' => $existing,
                    'assistant_message' => $assistant,
                    'job' => $assistant->intelligenceJob()->firstOrFail(),
                ];
            }

            $isFirst = ! $locked->messages()->exists();
            $prompt = $this->prompts->build($locked, $content);
            $userMessage = $locked->messages()->create([
                'role' => 'user',
                'content' => $content,
                'status' => 'completed',
                'client_request_id' => $clientRequestId,
                'completed_at' => now(),
            ]);
            $assistantMessage = $locked->messages()->create([
                'role' => 'assistant',
                'status' => 'queued',
            ]);
            $payload = [
                'purpose' => 'user_chat',
                'chat_message_public_id' => $assistantMessage->public_id,
                'prompt' => $prompt['prompt'],
                'system_prompt' => $prompt['system_prompt'],
                'response_format' => 'json',
                'max_tokens' => 256,
                'model_name' => mb_substr((string) config('services.private_worker.gateway_model', 'qwen3:1.7b'), 0, 120),
            ];
            $job = IntelligenceJob::query()->create([
                'public_id' => (string) Str::uuid(),
                'account_id' => $locked->account_id,
                'workspace_id' => $locked->workspace_id,
                'project_id' => $locked->project_id,
                'type' => 'local_llm',
                'status' => 'queued',
                'payload_json' => $payload,
                'input_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'available_at' => now(),
                'timeout_seconds' => 180,
                'max_attempts' => 2,
            ]);
            $assistantMessage->update(['intelligence_job_id' => $job->id]);
            $locked->update([
                'title' => $isFirst ? Str::limit($content, 160, '') : $locked->title,
                'last_message_at' => now(),
            ]);

            return [
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage->fresh(),
                'job' => $job,
            ];
        }, 3);
    }

    private function assertMembership(User $user, Workspace $workspace): void
    {
        $member = $workspace->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
        if (! $member) {
            throw new ApiException('ليست لديك صلاحية للوصول إلى مساحة العمل.', 'FORBIDDEN', 403);
        }
    }

    private function hasOnlineWorker(): bool
    {
        return (bool) config('services.private_worker.enabled', false)
            && IntelligenceWorker::query()
                ->where('status', 'active')
                ->where('last_seen_at', '>=', now()->subMinutes(5))
                ->whereJsonContains('capabilities_json', 'local_llm')
                ->exists();
    }
}
