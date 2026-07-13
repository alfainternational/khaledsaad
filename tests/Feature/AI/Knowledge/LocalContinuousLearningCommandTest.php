<?php

namespace Tests\Feature\AI\Knowledge;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\Tool\Models\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalContinuousLearningCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function distillation_uses_the_private_worker_without_external_provider_keys(): void
    {
        Storage::fake('local');
        config()->set('services.ai.provider', 'private_worker');
        config()->set('services.private_worker.enabled', true);
        config()->set('services.gemini.key', null);
        config()->set('services.nvidia.key', null);
        Tool::query()->create(['code' => 'retention', 'stage' => 1, 'status' => 'active']);
        $gateway = new class implements AiGatewayInterface
        {
            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                return json_encode([
                    'principles' => ['اربط كل توصية بمؤشر', 'اختبر السبب قبل الحل'],
                    'common_mistakes' => ['الاعتماد على الزيارات وحدها'],
                    'quick_win' => 'قس إعادة الشراء حسب الشريحة',
                    'key_metric' => 'معدل إعادة الشراء',
                ], JSON_UNESCAPED_UNICODE);
            }
        };
        $this->app->instance(AiGatewayInterface::class, $gateway);

        $this->artisan('ai:distill', ['limit' => 1])
            ->expectsOutput('ai:distill — قُطِّرت 1 playbook تسويقية.')
            ->assertSuccessful();

        $memory = app(KnowledgeStore::class)->recall('playbook.retention');
        $this->assertSame('معدل إعادة الشراء', $memory['data']['key_metric']);
    }
}
