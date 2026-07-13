<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Services\PrivateWorkerAiGateway;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateWorkerGenerationCanaryCommandTest extends TestCase
{
    #[Test]
    public function it_accepts_only_a_structured_local_response_with_the_requested_marker(): void
    {
        $gateway = new class extends PrivateWorkerAiGateway
        {
            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                return json_encode([
                    'canary' => 'LOCAL_REASONING_CANARY_20260713',
                    'analysis' => 'سبب واضح وتوصية قابلة للقياس',
                    '_model_name' => 'qwen-local',
                ], JSON_UNESCAPED_UNICODE);
            }
        };
        $this->app->instance(PrivateWorkerAiGateway::class, $gateway);

        $this->artisan('private-worker:generation-canary', ['--json' => true])
            ->assertSuccessful();
    }
}
