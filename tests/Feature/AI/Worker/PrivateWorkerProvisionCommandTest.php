<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateWorkerProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function provisioning_prints_a_one_time_secret_and_stores_only_its_ciphertext(): void
    {
        $exit = Artisan::call('private-worker:provision', [
            'name' => 'Owner Laptop',
            '--capability' => ['ocr', 'local_llm'],
            '--json' => true,
        ]);
        $credentials = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $worker = IntelligenceWorker::query()->where('public_id', $credentials['worker_id'])->firstOrFail();

        $this->assertSame(0, $exit);
        $this->assertNotSame($credentials['worker_secret'], $worker->secret_ciphertext);
        $this->assertSame($credentials['worker_secret'], Crypt::decryptString($worker->secret_ciphertext));
        $this->assertSame(['ocr', 'local_llm'], $worker->capabilities_json);
    }

    #[Test]
    public function disabling_a_worker_releases_its_active_leases(): void
    {
        Artisan::call('private-worker:provision', [
            'name' => 'Disable Me',
            '--capability' => ['ocr'],
            '--json' => true,
        ]);
        $credentials = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $worker = IntelligenceWorker::query()->where('public_id', $credentials['worker_id'])->firstOrFail();
        $job = $worker->jobs()->create([
            'public_id' => 'e8c892ab-d6dd-4d80-bd10-f7a245b5af81',
            'type' => 'ocr',
            'status' => 'leased',
            'lease_token_hash' => hash('sha256', 'lease'),
            'payload_json' => [],
            'leased_until' => now()->addMinute(),
        ]);

        $this->artisan('private-worker:disable', ['worker_id' => $worker->public_id])
            ->expectsOutput('Private worker disabled and active leases released.')
            ->assertSuccessful();

        $this->assertSame('disabled', $worker->fresh()->status);
        $this->assertSame('queued', IntelligenceJob::query()->findOrFail($job->id)->status);
        $this->assertNull(IntelligenceJob::query()->findOrFail($job->id)->intelligence_worker_id);
    }
}
