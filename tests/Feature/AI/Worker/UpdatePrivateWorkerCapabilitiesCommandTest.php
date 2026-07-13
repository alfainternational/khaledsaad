<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdatePrivateWorkerCapabilitiesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_allowed_capabilities_without_rotating_the_worker_secret(): void
    {
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_capability_update',
            'name' => 'Owner Worker',
            'secret_ciphertext' => 'encrypted-secret',
            'capabilities_json' => ['embeddings', 'local_llm'],
            'status' => 'active',
        ]);

        $this->artisan('private-worker:update-capabilities', [
            'worker' => $worker->public_id,
            '--capability' => ['ocr', 'document_extract', 'embeddings', 'local_llm'],
            '--json' => true,
        ])->assertSuccessful();

        $worker->refresh();
        $this->assertSame(['ocr', 'document_extract', 'embeddings', 'local_llm'], $worker->capabilities_json);
        $this->assertSame('encrypted-secret', $worker->secret_ciphertext);
    }
}
