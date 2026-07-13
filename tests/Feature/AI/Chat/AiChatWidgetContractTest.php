<?php

namespace Tests\Feature\AI\Chat;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiChatWidgetContractTest extends TestCase
{
    #[Test]
    public function the_web_widget_exposes_persistent_conversation_controls(): void
    {
        $blade = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("route('api.ai.conversations.index')", $blade);
        $this->assertStringContainsString('id="ai-chat-history"', $blade);
        $this->assertStringContainsString('id="ai-chat-new"', $blade);
        $this->assertStringContainsString('id="ai-chat-load-older"', $blade);
        $this->assertStringContainsString('data-conversations-url', $blade);
        $this->assertStringContainsString('client_request_id', $script);
        $this->assertStringContainsString('/messages/', $script);
    }
}
