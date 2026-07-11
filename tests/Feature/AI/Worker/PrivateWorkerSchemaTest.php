<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateWorkerSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function worker_control_plane_schema_supports_signed_leases_and_replay_protection(): void
    {
        $this->assertTrue(Schema::hasColumns('intelligence_workers', [
            'public_id', 'name', 'secret_ciphertext', 'capabilities_json', 'status',
            'version', 'last_seen_at', 'last_ip_hash', 'meta_json',
        ]));
        $this->assertTrue(Schema::hasColumns('intelligence_worker_nonces', [
            'intelligence_worker_id', 'nonce', 'request_timestamp', 'expires_at',
        ]));
        $this->assertTrue(Schema::hasColumns('intelligence_jobs', [
            'account_id', 'intelligence_worker_id', 'lease_token_hash', 'input_hash',
            'output_hash', 'model_name', 'model_version', 'timeout_seconds',
            'max_attempts', 'progress', 'lease_started_at',
        ]));
    }

    #[Test]
    public function worker_secrets_are_hidden_and_operational_fields_are_cast(): void
    {
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_test',
            'name' => 'Test Worker',
            'secret_ciphertext' => 'encrypted-value',
            'capabilities_json' => ['ocr', 'local_llm'],
            'status' => 'active',
            'last_seen_at' => now(),
            'meta_json' => ['host' => 'private'],
        ]);

        $array = $worker->fresh()->toArray();

        $this->assertArrayNotHasKey('secret_ciphertext', $array);
        $this->assertSame(['ocr', 'local_llm'], $worker->fresh()->capabilities_json);
        $this->assertInstanceOf(\DateTimeInterface::class, $worker->fresh()->last_seen_at);
    }
}
