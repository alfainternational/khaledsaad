<?php

namespace Tests\Feature\AI\Chat;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Chat\AsyncChatService;
use App\Domain\AI\Chat\Models\AiChatConversation;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Http\Api\ApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsyncChatServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_the_exchange_and_queues_local_generation_immediately(): void
    {
        [$user, $workspace] = $this->workspace();
        $this->onlineWorker();
        $service = app(AsyncChatService::class);
        $conversation = $service->createConversation($user, $workspace, null, 'general');

        $startedAt = microtime(true);
        $result = $service->send(
            $user,
            $workspace,
            $conversation,
            'كيف أرتب أولوياتي هذا الأسبوع؟',
            'request-1',
        );
        $elapsed = microtime(true) - $startedAt;

        $this->assertSame('completed', $result['user_message']->status);
        $this->assertSame('queued', $result['assistant_message']->status);
        $this->assertSame('كيف أرتب أولوياتي هذا الأسبوع؟', $result['user_message']->content);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame('كيف أرتب أولوياتي هذا الأسبوع؟', $conversation->fresh()->title);

        $job = IntelligenceJob::query()->where('payload_json->purpose', 'user_chat')->sole();
        $this->assertSame('local_llm', $job->type);
        $this->assertSame('user_chat', $job->payload_json['purpose']);
        $this->assertSame($result['assistant_message']->public_id, $job->payload_json['chat_message_public_id']);
        $this->assertSame($workspace->id, $job->workspace_id);
        $this->assertSame($job->id, $result['assistant_message']->intelligence_job_id);
        $this->assertSame(1, IntelligenceJob::query()->where('type', 'local_llm')->count());
        $this->assertLessThan(2.0, $elapsed, 'Dispatch must not wait for a second AI generation.');
    }

    #[Test]
    public function it_uses_only_the_latest_twenty_completed_messages_in_the_model_prompt(): void
    {
        [$user, $workspace] = $this->workspace();
        $this->onlineWorker();
        $service = app(AsyncChatService::class);
        $conversation = $service->createConversation($user, $workspace, null, 'general');
        foreach (range(1, 24) as $index) {
            $conversation->messages()->create([
                'role' => $index % 2 === 0 ? 'assistant' : 'user',
                'content' => "history-marker-{$index}",
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        $service->send($user, $workspace, $conversation, 'current-marker', 'request-window');

        $prompt = (string) IntelligenceJob::query()->where('payload_json->purpose', 'user_chat')->sole()->payload_json['prompt'];
        $this->assertStringNotContainsString("history-marker-1\n", $prompt);
        $this->assertStringNotContainsString("history-marker-4\n", $prompt);
        $this->assertStringContainsString('history-marker-5', $prompt);
        $this->assertStringContainsString('history-marker-24', $prompt);
        $this->assertStringContainsString('current-marker', $prompt);
        $this->assertSame(26, $conversation->messages()->count(), 'The complete history must remain stored.');
    }

    #[Test]
    public function duplicate_client_request_returns_the_existing_message_pair(): void
    {
        [$user, $workspace] = $this->workspace();
        $this->onlineWorker();
        $service = app(AsyncChatService::class);
        $conversation = $service->createConversation($user, $workspace, null, 'general');

        $first = $service->send($user, $workspace, $conversation, 'رسالة واحدة', 'same-request');
        $second = $service->send($user, $workspace, $conversation, 'رسالة واحدة', 'same-request');

        $this->assertSame($first['user_message']->id, $second['user_message']->id);
        $this->assertSame($first['assistant_message']->id, $second['assistant_message']->id);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(1, IntelligenceJob::query()->where('payload_json->purpose', 'user_chat')->count());
    }

    #[Test]
    public function unavailable_worker_does_not_create_false_pending_history(): void
    {
        [$user, $workspace] = $this->workspace();
        $service = app(AsyncChatService::class);
        $conversation = $service->createConversation($user, $workspace, null, 'general');

        try {
            $service->send($user, $workspace, $conversation, 'هل أنت متاح؟', 'request-offline');
            $this->fail('Expected the unavailable worker exception.');
        } catch (ApiException $exception) {
            $this->assertSame('AI_WORKER_UNAVAILABLE', $exception->errorCode);
            $this->assertSame(503, $exception->status);
        }

        $this->assertDatabaseCount('ai_chat_messages', 0);
        $this->assertDatabaseCount('intelligence_jobs', 0);
    }

    /** @return array{User, Workspace} */
    private function workspace(): array
    {
        config()->set('services.private_worker.enabled', true);
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
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return [$user, $workspace];
    }

    private function onlineWorker(): void
    {
        IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => 'Online Chat Worker',
            'secret_ciphertext' => Crypt::encryptString(Str::random(64)),
            'capabilities_json' => ['local_llm'],
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
    }
}
