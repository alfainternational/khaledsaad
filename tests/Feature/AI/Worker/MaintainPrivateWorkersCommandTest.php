<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\Models\IntelligenceWorkerNonce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintainPrivateWorkersCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function maintenance_cleans_nonces_and_recovers_or_fails_expired_leases(): void
    {
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => 'Maintenance Worker',
            'secret_ciphertext' => Crypt::encryptString(Str::random(64)),
            'capabilities_json' => ['ocr'],
            'status' => 'active',
        ]);
        IntelligenceWorkerNonce::query()->create([
            'intelligence_worker_id' => $worker->id,
            'nonce' => (string) Str::uuid(),
            'request_timestamp' => now()->subHour()->timestamp,
            'expires_at' => now()->subMinute(),
        ]);
        $requeue = $this->job($worker, 1, 3);
        $terminal = $this->job($worker, 3, 3);

        $this->artisan('private-worker:maintain')
            ->expectsOutput('Nonces removed: 1; jobs requeued: 1; jobs failed: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('intelligence_worker_nonces', 0);
        $this->assertSame('queued', $requeue->fresh()->status);
        $this->assertNull($requeue->fresh()->intelligence_worker_id);
        $this->assertSame('failed', $terminal->fresh()->status);
        $this->assertSame($worker->id, $terminal->fresh()->intelligence_worker_id);
    }

    private function job(IntelligenceWorker $worker, int $attempts, int $maxAttempts): IntelligenceJob
    {
        return IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'intelligence_worker_id' => $worker->id,
            'type' => 'ocr',
            'status' => 'leased',
            'lease_token_hash' => hash('sha256', Str::random(32)),
            'payload_json' => [],
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'lease_started_at' => now()->subMinutes(5),
            'leased_until' => now()->subMinute(),
        ]);
    }
}
