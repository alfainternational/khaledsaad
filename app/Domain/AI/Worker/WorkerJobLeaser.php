<?php

namespace App\Domain\AI\Worker;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\Security\WorkerSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkerJobLeaser
{
    public function __construct(private readonly WorkerSigner $signer) {}

    /** @return array{job: array<string, mixed>, lease_token: string, job_signature: string}|null */
    public function lease(
        IntelligenceWorker $worker,
        string $secret,
        array $requestedCapabilities,
        array $runtime = [],
    ): ?array {
        $allowed = array_values(array_intersect(
            $this->capabilities($worker->capabilities_json),
            $this->capabilities($requestedCapabilities),
        ));
        if ($allowed === []) {
            throw new InvalidArgumentException('The worker did not request an allowed capability.');
        }

        if ($runtime !== []) {
            $meta = is_array($worker->meta_json) ? $worker->meta_json : [];
            $meta['runtime'] = $runtime;
            $worker->update(['meta_json' => $meta]);
        }

        return DB::transaction(function () use ($worker, $secret, $allowed): ?array {
            $job = IntelligenceJob::query()
                ->where('status', 'queued')
                ->whereIn('type', $allowed)
                ->whereColumn('attempts', '<', 'max_attempts')
                ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if (! $job) {
                return null;
            }

            $leaseToken = Str::random(64);
            $attempt = $job->attempts + 1;
            $inputHash = $job->input_hash ?: hash('sha256', $this->signer->canonicalJson($job->payload_json));
            $leaseSeconds = min(
                $job->timeout_seconds,
                (int) config('services.private_worker.lease_seconds', 120),
            );
            $job->update([
                'intelligence_worker_id' => $worker->id,
                'status' => 'leased',
                'lease_token_hash' => hash('sha256', $leaseToken),
                'input_hash' => $inputHash,
                'attempts' => $attempt,
                'lease_started_at' => now(),
                'leased_until' => now()->addSeconds($leaseSeconds),
                'progress' => 0,
                'last_error' => null,
            ]);

            $envelope = [
                'public_id' => $job->public_id,
                'type' => $job->type,
                'input_hash' => $inputHash,
                'timeout_seconds' => $job->timeout_seconds,
                'attempt' => $attempt,
                'payload' => $this->safePayload($job->payload_json),
            ];

            return [
                'job' => $envelope,
                'lease_token' => $leaseToken,
                'job_signature' => $this->signer->signEnvelope($secret, $envelope),
            ];
        }, 3);
    }

    /** @return list<string> */
    private function capabilities(mixed $capabilities): array
    {
        return collect(is_array($capabilities) ? $capabilities : [])
            ->filter(fn ($value): bool => is_string($value) && preg_match('/\A[a-z0-9_.-]{2,48}\z/D', $value) === 1)
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function safePayload(array $payload): array
    {
        $blocked = ['path', 'disk', 'secret', 'password', 'token', 'api_key', 'authorization', 'credentials'];
        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                continue;
            }
            $clean[$key] = is_array($value) ? $this->safePayload($value) : $value;
        }

        return $clean;
    }
}
