<?php

namespace Tests\Feature\AI\Chat;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Chat\Models\AiChatConversation;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiChatHistorySchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chat_history_is_owned_by_a_user_inside_a_workspace(): void
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
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $conversation = AiChatConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'اختبار السجل',
            'tool_key' => 'general',
            'last_message_at' => now(),
        ]);
        $message = $conversation->messages()->create([
            'public_id' => (string) Str::uuid(),
            'role' => 'user',
            'content' => 'رسالة محفوظة',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->assertTrue($message->conversation->is($conversation));
        $this->assertTrue($conversation->user->is($user));
        $this->assertTrue($conversation->workspace->is($workspace));
        $this->assertSame('رسالة محفوظة', $conversation->messages()->firstOrFail()->content);
    }
}
