<?php

namespace Tests\Feature\AI\Chat;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Chat\Models\AiChatConversation;
use App\Domain\AI\Chat\Models\AiChatMessage;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\WorkerJobLifecycle;
use App\Domain\AI\Worker\WorkerProtocolException;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatWorkerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_local_chat_result_completes_the_pending_assistant_message(): void
    {
        [$message, $job, $worker, $leaseToken] = $this->pendingChatJob();

        app(WorkerJobLifecycle::class)->complete(
            $worker,
            $job->public_id,
            $leaseToken,
            ['answer' => 'هذه إجابة عربية طبيعية.', '_model_name' => 'qwen3:1.7b'],
            'qwen3:1.7b',
            'qwen3:1.7b',
        );

        $message->refresh();
        $this->assertSame('completed', $message->status);
        $this->assertSame('هذه إجابة عربية طبيعية.', $message->content);
        $this->assertSame('qwen3:1.7b', $message->meta_json['model_name']);
        $this->assertNotNull($message->completed_at);
    }

    #[Test]
    public function a_chat_result_cannot_cross_its_workspace_scope(): void
    {
        [, $job, $worker, $leaseToken] = $this->pendingChatJob();
        $otherWorkspace = Workspace::query()->create([
            'account_id' => $job->account_id,
            'name' => 'Other Workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);
        $job->update(['workspace_id' => $otherWorkspace->id]);

        $this->expectException(WorkerProtocolException::class);
        $this->expectExceptionMessage('tenant');

        app(WorkerJobLifecycle::class)->complete(
            $worker,
            $job->public_id,
            $leaseToken,
            ['answer' => 'يجب ألا تحفظ.'],
            'qwen3:1.7b',
            'qwen3:1.7b',
        );
    }

    #[Test]
    public function a_terminal_worker_failure_marks_the_assistant_message_failed(): void
    {
        [$message, $job, $worker, $leaseToken] = $this->pendingChatJob();

        app(WorkerJobLifecycle::class)->fail(
            $worker,
            $job->public_id,
            $leaseToken,
            'LOCAL_MODEL_TIMEOUT',
            'internal timeout detail',
        );

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('AI_RESPONSE_FAILED', $message->error_code);
        $this->assertStringNotContainsString('internal timeout', (string) $message->error_message);
    }

    /** @return array{AiChatMessage, IntelligenceJob, IntelligenceWorker, string} */
    private function pendingChatJob(): array
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Chat Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Chat Workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);
        $conversation = AiChatConversation::query()->create([
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'محادثة اختبار',
            'tool_key' => 'general',
            'last_message_at' => now(),
        ]);
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => 'queued',
        ]);
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => 'Chat Worker',
            'secret_ciphertext' => Crypt::encryptString(Str::random(64)),
            'capabilities_json' => ['local_llm'],
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        $leaseToken = Str::random(64);
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'intelligence_worker_id' => $worker->id,
            'type' => 'local_llm',
            'status' => 'leased',
            'lease_token_hash' => hash('sha256', $leaseToken),
            'payload_json' => [
                'purpose' => 'user_chat',
                'chat_message_public_id' => $message->public_id,
            ],
            'attempts' => 1,
            'max_attempts' => 1,
            'timeout_seconds' => 120,
            'lease_started_at' => now(),
            'leased_until' => now()->addMinute(),
        ]);
        $message->update(['intelligence_job_id' => $job->id]);

        return [$message, $job, $worker, $leaseToken];
    }
}
