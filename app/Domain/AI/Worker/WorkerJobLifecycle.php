<?php

namespace App\Domain\AI\Worker;

use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\Security\WorkerSigner;
use Illuminate\Support\Facades\DB;

class WorkerJobLifecycle
{
    public function __construct(
        private readonly WorkerSigner $signer,
        private readonly WorkerResultApplier $resultApplier,
    ) {}

    public function heartbeat(
        IntelligenceWorker $worker,
        string $jobPublicId,
        string $leaseToken,
        int $progress,
    ): IntelligenceJob {
        return DB::transaction(function () use ($worker, $jobPublicId, $leaseToken, $progress): IntelligenceJob {
            $job = $this->leasedJob($worker, $jobPublicId, $leaseToken, true);
            $deadline = $job->lease_started_at->copy()->addSeconds($job->timeout_seconds);
            if (now()->greaterThanOrEqualTo($deadline)) {
                throw new WorkerProtocolException('WORKER_LEASE_EXPIRED', 409, 'The job execution deadline has passed.');
            }

            $leaseUntil = now()->addSeconds((int) config('services.private_worker.lease_seconds', 120));
            if ($leaseUntil->greaterThan($deadline)) {
                $leaseUntil = $deadline;
            }
            $job->update([
                'progress' => max($job->progress, min(99, $progress)),
                'leased_until' => $leaseUntil,
            ]);

            return $job->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $result */
    public function complete(
        IntelligenceWorker $worker,
        string $jobPublicId,
        string $leaseToken,
        array $result,
        ?string $modelName,
        ?string $modelVersion,
    ): array {
        return DB::transaction(function () use (
            $worker,
            $jobPublicId,
            $leaseToken,
            $result,
            $modelName,
            $modelVersion,
        ): array {
            $job = IntelligenceJob::query()
                ->where('public_id', $jobPublicId)
                ->where('intelligence_worker_id', $worker->id)
                ->lockForUpdate()
                ->first();
            if (! $job || ! $this->validToken($job, $leaseToken)) {
                throw new WorkerProtocolException('WORKER_LEASE_INVALID', 409, 'The job lease is invalid.');
            }

            $outputHash = hash('sha256', $this->signer->canonicalJson($result));
            if ($job->status === 'completed') {
                if (! hash_equals((string) $job->output_hash, $outputHash)) {
                    throw new WorkerProtocolException('WORKER_RESULT_CONFLICT', 409, 'The completed job has a different output.');
                }

                return ['job' => $job, 'idempotent' => true];
            }
            if ($job->status !== 'leased' || $job->leased_until === null || $job->leased_until->isPast()) {
                throw new WorkerProtocolException('WORKER_LEASE_EXPIRED', 409, 'The job lease has expired.');
            }

            $this->resultApplier->apply($job, $worker, $result);
            $job->update([
                'status' => 'completed',
                'result_json' => $result,
                'output_hash' => $outputHash,
                'model_name' => $modelName,
                'model_version' => $modelVersion,
                'progress' => 100,
                'leased_until' => null,
                'completed_at' => now(),
                'last_error' => null,
            ]);

            return ['job' => $job->fresh(), 'idempotent' => false];
        }, 3);
    }

    public function fail(
        IntelligenceWorker $worker,
        string $jobPublicId,
        string $leaseToken,
        string $errorCode,
        string $message,
    ): IntelligenceJob {
        return DB::transaction(function () use ($worker, $jobPublicId, $leaseToken, $errorCode, $message): IntelligenceJob {
            $job = $this->leasedJob($worker, $jobPublicId, $leaseToken, false);
            if ($job->status !== 'leased') {
                throw new WorkerProtocolException('WORKER_LEASE_INVALID', 409, 'The job is not actively leased.');
            }
            $terminal = $job->attempts >= $job->max_attempts;
            $job->update([
                'status' => $terminal ? 'failed' : 'queued',
                'intelligence_worker_id' => $terminal ? $worker->id : null,
                'lease_token_hash' => $terminal ? $job->lease_token_hash : null,
                'lease_started_at' => null,
                'leased_until' => null,
                'progress' => 0,
                'available_at' => $terminal ? null : now()->addSeconds(min(300, (2 ** $job->attempts) * 15)),
                'last_error' => $errorCode.': '.mb_substr($message, 0, 800),
            ]);

            return $job->fresh();
        }, 3);
    }

    public function inputUpload(
        IntelligenceWorker $worker,
        string $jobPublicId,
        string $leaseToken,
    ): KnowledgeUpload {
        $job = $this->leasedJob($worker, $jobPublicId, $leaseToken, true);
        $uploadPublicId = $job->payload_json['upload_public_id'] ?? null;
        if (! is_string($uploadPublicId) || $uploadPublicId === '') {
            throw new WorkerProtocolException('WORKER_INPUT_UNAVAILABLE', 404, 'The job has no downloadable input.');
        }

        $upload = KnowledgeUpload::query()
            ->where('public_id', $uploadPublicId)
            ->where('account_id', $job->account_id)
            ->where('workspace_id', $job->workspace_id)
            ->where('project_id', $job->project_id)
            ->first();
        if (! $upload) {
            throw new WorkerProtocolException('WORKER_INPUT_UNAVAILABLE', 404, 'The job input was not found.');
        }

        return $upload;
    }

    private function leasedJob(
        IntelligenceWorker $worker,
        string $publicId,
        string $leaseToken,
        bool $requireActive,
    ): IntelligenceJob {
        $job = IntelligenceJob::query()
            ->where('public_id', $publicId)
            ->where('intelligence_worker_id', $worker->id)
            ->first();
        if (! $job || ! $this->validToken($job, $leaseToken)) {
            throw new WorkerProtocolException('WORKER_LEASE_INVALID', 409, 'The job lease is invalid.');
        }
        if ($requireActive && ($job->status !== 'leased' || $job->leased_until === null || $job->leased_until->isPast())) {
            throw new WorkerProtocolException('WORKER_LEASE_EXPIRED', 409, 'The job lease has expired.');
        }

        return $job;
    }

    private function validToken(IntelligenceJob $job, string $leaseToken): bool
    {
        return is_string($job->lease_token_hash)
            && $job->lease_token_hash !== ''
            && hash_equals($job->lease_token_hash, hash('sha256', $leaseToken));
    }
}
