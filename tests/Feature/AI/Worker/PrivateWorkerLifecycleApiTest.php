<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\Security\WorkerSigner;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateWorkerLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function leased_worker_can_heartbeat_download_input_and_complete_idempotently(): void
    {
        Storage::fake('local');
        config()->set('services.private_worker.enabled', true);
        [$worker, $secret] = $this->worker();
        [$upload, $job] = $this->uploadJob();
        [$leaseToken] = $this->lease($worker, $secret);

        $heartbeatPath = '/api/v1/private-worker/jobs/'.$job->public_id.'/heartbeat';
        $heartbeatBody = json_encode(['lease_token' => $leaseToken, 'progress' => 40], JSON_THROW_ON_ERROR);
        $this->signedCall($worker, $secret, 'POST', $heartbeatPath, $heartbeatBody)
            ->assertOk()
            ->assertJsonPath('data.progress', 40);

        $inputPath = '/api/v1/private-worker/jobs/'.$job->public_id.'/input';
        $input = $this->signedCall(
            $worker,
            $secret,
            'GET',
            $inputPath,
            '',
            ['HTTP_X_WORKER_LEASE_TOKEN' => $leaseToken],
        )->assertOk();
        $this->assertSame(Storage::disk('local')->get($upload->path), $input->streamedContent());

        $completePath = '/api/v1/private-worker/jobs/'.$job->public_id.'/complete';
        $result = ['text' => 'نتيجة محلية موثقة', 'confidence' => 0.92];
        $completeBody = json_encode([
            'lease_token' => $leaseToken,
            'result' => $result,
            'model_name' => 'qwen-local',
            'model_version' => '1',
        ], JSON_THROW_ON_ERROR);
        $this->signedCall($worker, $secret, 'POST', $completePath, $completeBody)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->signedCall($worker, $secret, 'POST', $completePath, $completeBody)
            ->assertOk()
            ->assertJsonPath('data.idempotent', true);

        $different = json_encode([
            'lease_token' => $leaseToken,
            'result' => ['text' => 'نتيجة مختلفة'],
        ], JSON_THROW_ON_ERROR);
        $this->signedCall($worker, $secret, 'POST', $completePath, $different)
            ->assertStatus(409)
            ->assertJsonPath('code', 'WORKER_RESULT_CONFLICT');

        $this->assertDatabaseHas('intelligence_jobs', [
            'id' => $job->id,
            'status' => 'completed',
            'progress' => 100,
            'model_name' => 'qwen-local',
        ]);
        $this->assertSame('indexed', $upload->fresh()->status);
        $this->assertDatabaseHas('knowledge_documents', [
            'knowledge_source_id' => $upload->fresh()->knowledge_source_id,
            'status' => 'active',
            'content' => 'نتيجة محلية موثقة',
        ]);
    }

    #[Test]
    public function wrong_lease_is_rejected_and_failure_requeues_until_attempt_limit(): void
    {
        config()->set('services.private_worker.enabled', true);
        [$worker, $secret] = $this->worker();
        [, $job] = $this->uploadJob();
        [$leaseToken] = $this->lease($worker, $secret);
        $path = '/api/v1/private-worker/jobs/'.$job->public_id.'/heartbeat';
        $wrong = json_encode(['lease_token' => 'wrong-token-value', 'progress' => 20], JSON_THROW_ON_ERROR);

        $this->signedCall($worker, $secret, 'POST', $path, $wrong)
            ->assertStatus(409)
            ->assertJsonPath('code', 'WORKER_LEASE_INVALID');

        $failPath = '/api/v1/private-worker/jobs/'.$job->public_id.'/fail';
        $failBody = json_encode([
            'lease_token' => $leaseToken,
            'error_code' => 'OCR_TIMEOUT',
            'message' => 'OCR command exceeded its local timeout.',
        ], JSON_THROW_ON_ERROR);
        $this->signedCall($worker, $secret, 'POST', $failPath, $failBody)
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('intelligence_jobs', [
            'id' => $job->id,
            'status' => 'queued',
            'intelligence_worker_id' => null,
        ]);
    }

    /** @return array{IntelligenceWorker, string} */
    private function worker(): array
    {
        $secret = Str::random(64);

        return [
            IntelligenceWorker::query()->create([
                'public_id' => 'wrk_'.Str::lower((string) Str::ulid()),
                'name' => 'Lifecycle Worker',
                'secret_ciphertext' => Crypt::encryptString($secret),
                'capabilities_json' => ['ocr'],
                'status' => 'active',
            ]),
            $secret,
        ];
    }

    /** @return array{KnowledgeUpload, IntelligenceJob} */
    private function uploadJob(): array
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Worker Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Worker Workspace',
            'type' => 'team',
            'status' => 'active',
        ]);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Worker Client',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Worker Project',
            'stage' => 1,
            'status' => 'active',
        ]);
        $path = 'knowledge-uploads/worker/input.txt';
        Storage::disk('local')->put($path, 'private worker input');
        $upload = KnowledgeUpload::query()->create([
            'public_id' => 'upl_worker_input',
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'input.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'byte_size' => 20,
            'sha256' => hash('sha256', 'private worker input'),
            'status' => 'stored',
        ]);
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'type' => 'ocr',
            'status' => 'queued',
            'payload_json' => ['upload_public_id' => $upload->public_id],
            'available_at' => now(),
            'max_attempts' => 3,
        ]);

        return [$upload, $job];
    }

    /** @return array{string, string} */
    private function lease(IntelligenceWorker $worker, string $secret): array
    {
        $path = '/api/v1/private-worker/lease';
        $body = json_encode(['capabilities' => ['ocr']], JSON_THROW_ON_ERROR);
        $response = $this->signedCall($worker, $secret, 'POST', $path, $body)->assertOk();

        return [$response->json('data.lease_token'), $response->json('data.job.public_id')];
    }

    private function signedCall(
        IntelligenceWorker $worker,
        string $secret,
        string $method,
        string $path,
        string $body,
        array $extra = [],
    ) {
        $timestamp = now()->timestamp;
        $nonce = (string) Str::uuid();
        $headers = $extra + [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WORKER_ID' => $worker->public_id,
            'HTTP_X_WORKER_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WORKER_NONCE' => $nonce,
            'HTTP_X_WORKER_SIGNATURE' => app(WorkerSigner::class)->signRequest(
                $secret,
                $method,
                $path,
                $timestamp,
                $nonce,
                $body,
            ),
        ];

        return $this->call($method, $path, [], [], [], $headers, $body);
    }
}
