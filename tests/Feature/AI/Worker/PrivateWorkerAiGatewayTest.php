<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Services\PrivateWorkerAiGateway;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateWorkerAiGatewayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_null_without_an_online_local_model_worker(): void
    {
        config()->set('services.private_worker.enabled', true);

        $response = app(PrivateWorkerAiGateway::class)->requestContent('حلل هذه البيانات');

        $this->assertNull($response);
        $this->assertDatabaseCount('intelligence_jobs', 0);
    }

    #[Test]
    public function it_queues_local_generation_and_returns_the_structured_worker_result(): void
    {
        config()->set('services.private_worker.enabled', true);
        config()->set('services.private_worker.gateway_wait_seconds', 2);
        IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => 'Local Model Worker',
            'secret_ciphertext' => Crypt::encryptString(Str::random(64)),
            'capabilities_json' => ['local_llm'],
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        $gateway = new PrivateWorkerAiGateway(function (IntelligenceJob $job): void {
            $job->update([
                'status' => 'completed',
                'result_json' => ['headline' => 'تحليل محلي', 'text' => 'نتيجة موثقة'],
                'output_hash' => hash('sha256', 'result'),
                'completed_at' => now(),
            ]);
        });

        $response = $gateway->requestContent('حلل هذه البيانات', 'أعد JSON');

        $decoded = json_decode($response['choices'][0]['message']['content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('تحليل محلي', $decoded['headline']);
        $this->assertDatabaseHas('intelligence_jobs', [
            'type' => 'local_llm',
            'status' => 'completed',
        ]);
        $payload = IntelligenceJob::query()->firstOrFail()->payload_json;
        $this->assertSame('حلل هذه البيانات', $payload['prompt']);
        $this->assertSame('أعد JSON', $payload['system_prompt']);
    }
}
