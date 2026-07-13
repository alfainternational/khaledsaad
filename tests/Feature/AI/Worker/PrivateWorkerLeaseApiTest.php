<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\Security\WorkerSigner;
use App\Support\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateWorkerLeaseApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_signed_worker_leases_a_job_once_and_receives_a_signed_safe_envelope(): void
    {
        config()->set('services.private_worker.enabled', true);
        [$worker, $secret] = $this->worker(['ocr']);
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'type' => 'ocr',
            'status' => 'queued',
            'payload_json' => ['upload_public_id' => 'upl_safe', 'path' => '/must/not/leak'],
            'input_hash' => hash('sha256', 'input'),
            'available_at' => now(),
        ]);
        $body = json_encode([
            'capabilities' => ['ocr'],
            'version' => 'worker-test',
            'runtime' => [
                'python' => '3.13.1',
                'tools' => ['tesseract' => '5.5.0', 'pdftotext' => '24.08.0'],
                'ocr_languages' => ['ara', 'eng'],
            ],
        ], JSON_THROW_ON_ERROR);
        $headers = $this->signedHeaders($worker, $secret, 'POST', '/api/v1/private-worker/lease', $body);

        $response = $this->call('POST', '/api/v1/private-worker/lease', [], [], [], $headers, $body)
            ->assertOk()
            ->assertJsonPath('data.job.public_id', $job->public_id)
            ->assertJsonPath('data.job.type', 'ocr');

        $this->assertNotEmpty($response->json('data.lease_token'));
        $this->assertNotEmpty($response->json('data.job_signature'));
        $this->assertArrayNotHasKey('path', $response->json('data.job.payload'));
        $this->assertDatabaseHas('intelligence_jobs', [
            'id' => $job->id,
            'status' => 'leased',
            'intelligence_worker_id' => $worker->id,
            'attempts' => 1,
        ]);
        $this->assertSame('5.5.0', $worker->fresh()->meta_json['runtime']['tools']['tesseract']);
        $this->assertSame(['ara', 'eng'], $worker->fresh()->meta_json['runtime']['ocr_languages']);

        $this->call('POST', '/api/v1/private-worker/lease', [], [], [], $headers, $body)
            ->assertStatus(409)
            ->assertJsonPath('code', 'WORKER_NONCE_REPLAYED');

        $freshHeaders = $this->signedHeaders($worker, $secret, 'POST', '/api/v1/private-worker/lease', $body);
        $this->call('POST', '/api/v1/private-worker/lease', [], [], [], $freshHeaders, $body)
            ->assertNoContent();
    }

    #[Test]
    public function invalid_stale_and_disabled_worker_requests_are_rejected(): void
    {
        config()->set('services.private_worker.enabled', true);
        [$worker, $secret] = $this->worker(['ocr']);
        $body = json_encode(['capabilities' => ['ocr']], JSON_THROW_ON_ERROR);

        $invalid = $this->signedHeaders($worker, $secret, 'POST', '/api/v1/private-worker/lease', $body);
        $invalid['HTTP_X_WORKER_SIGNATURE'] = str_repeat('0', 64);
        $this->call('POST', '/api/v1/private-worker/lease', [], [], [], $invalid, $body)
            ->assertUnauthorized()
            ->assertJsonPath('code', 'WORKER_SIGNATURE_INVALID');

        $stale = $this->signedHeaders(
            $worker,
            $secret,
            'POST',
            '/api/v1/private-worker/lease',
            $body,
            now()->subMinutes(10)->timestamp,
        );
        $this->call('POST', '/api/v1/private-worker/lease', [], [], [], $stale, $body)
            ->assertUnauthorized()
            ->assertJsonPath('code', 'WORKER_TIMESTAMP_INVALID');

        config()->set('services.private_worker.enabled', false);
        $this->call(
            'POST',
            '/api/v1/private-worker/lease',
            [],
            [],
            [],
            $this->signedHeaders($worker, $secret, 'POST', '/api/v1/private-worker/lease', $body),
            $body,
        )->assertNotFound();
    }

    #[Test]
    public function fresh_runtime_setting_overrides_stale_process_configuration(): void
    {
        Storage::fake('local');
        [$worker, $secret] = $this->worker(['ocr']);
        $body = json_encode(['capabilities' => ['ocr']], JSON_THROW_ON_ERROR);

        config()->set('services.private_worker.enabled', false);
        app(SettingsStore::class)->set('services.private_worker.enabled', true);
        $this->call(
            'POST',
            '/api/v1/private-worker/lease',
            [],
            [],
            [],
            $this->signedHeaders($worker, $secret, 'POST', '/api/v1/private-worker/lease', $body),
            $body,
        )->assertNoContent();

        config()->set('services.private_worker.enabled', true);
        app(SettingsStore::class)->set('services.private_worker.enabled', false);
        $this->call(
            'POST',
            '/api/v1/private-worker/lease',
            [],
            [],
            [],
            $this->signedHeaders($worker, $secret, 'POST', '/api/v1/private-worker/lease', $body),
            $body,
        )->assertNotFound();
    }

    /** @return array{IntelligenceWorker, string} */
    private function worker(array $capabilities): array
    {
        $secret = Str::random(64);
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
            'name' => 'Lease Worker',
            'secret_ciphertext' => Crypt::encryptString($secret),
            'capabilities_json' => $capabilities,
            'status' => 'active',
        ]);

        return [$worker, $secret];
    }

    /** @return array<string, string> */
    private function signedHeaders(
        IntelligenceWorker $worker,
        string $secret,
        string $method,
        string $path,
        string $body,
        ?int $timestamp = null,
    ): array {
        $timestamp ??= now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = app(WorkerSigner::class)->signRequest($secret, $method, $path, $timestamp, $nonce, $body);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WORKER_ID' => $worker->public_id,
            'HTTP_X_WORKER_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WORKER_NONCE' => $nonce,
            'HTTP_X_WORKER_SIGNATURE' => $signature,
            'HTTP_X_WORKER_VERSION' => 'worker-test',
        ];
    }
}
