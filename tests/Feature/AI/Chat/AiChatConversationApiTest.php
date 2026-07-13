<?php

namespace Tests\Feature\AI\Chat;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Chat\AsyncChatService;
use App\Domain\AI\Chat\Models\AiChatConversation;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiChatConversationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function web_user_can_create_send_poll_and_read_their_complete_paginated_history(): void
    {
        [$user, $workspace] = $this->tenant();
        $this->onlineWorker();

        $created = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson('/api/ai/conversations', ['tool_key' => 'general'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'محادثة جديدة');
        $conversationId = $created->json('data.public_id');

        $sent = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson("/api/ai/conversations/{$conversationId}/messages", [
                'content' => 'كيف أبدأ؟',
                'client_request_id' => 'web-request-1',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.user_message.content', 'كيف أبدأ؟')
            ->assertJsonPath('data.assistant_message.status', 'queued');
        $assistantId = $sent->json('data.assistant_message.public_id');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson("/api/ai/conversations/{$conversationId}/messages/{$assistantId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');

        $conversation = AiChatConversation::query()->where('public_id', $conversationId)->sole();
        foreach (range(1, 45) as $index) {
            $conversation->messages()->create([
                'role' => $index % 2 ? 'user' : 'assistant',
                'content' => "archived-message-{$index}",
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson("/api/ai/conversations/{$conversationId}?per_page=20&page=3")
            ->assertOk()
            ->assertJsonPath('data.public_id', $conversationId)
            ->assertJsonPath('messages.meta.total', 47)
            ->assertJsonPath('messages.meta.current_page', 3)
            ->assertJsonCount(7, 'messages.data');
    }

    #[Test]
    public function mobile_api_exposes_the_same_private_history_and_rejects_another_workspace_member(): void
    {
        [$owner, $workspace] = $this->tenant();
        $other = User::factory()->create();
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $other->id,
            'role' => 'editor',
            'status' => 'active',
        ]);
        $conversation = app(AsyncChatService::class)->createConversation($owner, $workspace);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'سجل خاص بالمالك',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $ownerToken = $owner->createToken('owner-chat')->plainTextToken;
        $otherToken = $other->createToken('other-chat')->plainTextToken;
        $base = '/api/v1/workspaces/'.$workspace->public_id.'/ai/conversations';

        $this->withToken($ownerToken)
            ->getJson($base)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $conversation->public_id);

        $this->withToken($otherToken)
            ->getJson($base.'/'.$conversation->public_id)
            ->assertNotFound();
    }

    /** @return array{User, Workspace} */
    private function tenant(): array
    {
        config()->set('services.private_worker.enabled', true);
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Private Chat Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Private Chat Workspace',
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
