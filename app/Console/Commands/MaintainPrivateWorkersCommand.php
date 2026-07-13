<?php

namespace App\Console\Commands;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorkerNonce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MaintainPrivateWorkersCommand extends Command
{
    protected $signature = 'private-worker:maintain';

    protected $description = 'Clean worker nonces and recover expired private-worker job leases';

    public function handle(): int
    {
        $nonces = IntelligenceWorkerNonce::query()->where('expires_at', '<=', now())->delete();
        $requeued = 0;
        $failed = 0;

        IntelligenceJob::query()
            ->where('status', 'leased')
            ->whereNotNull('leased_until')
            ->where('leased_until', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use (&$requeued, &$failed): void {
                foreach ($jobs as $candidate) {
                    DB::transaction(function () use ($candidate, &$requeued, &$failed): void {
                        $job = IntelligenceJob::query()->lockForUpdate()->find($candidate->id);
                        if (! $job || $job->status !== 'leased' || $job->leased_until?->isFuture()) {
                            return;
                        }
                        $terminal = $job->attempts >= $job->max_attempts;
                        $job->update([
                            'status' => $terminal ? 'failed' : 'queued',
                            'intelligence_worker_id' => $terminal ? $job->intelligence_worker_id : null,
                            'lease_token_hash' => $terminal ? $job->lease_token_hash : null,
                            'lease_started_at' => null,
                            'leased_until' => null,
                            'progress' => 0,
                            'available_at' => $terminal ? null : now(),
                            'last_error' => 'WORKER_LEASE_EXPIRED: private worker did not finish before its lease expired.',
                        ]);
                        $terminal ? $failed++ : $requeued++;
                    });
                }
            });

        $this->line("Nonces removed: {$nonces}; jobs requeued: {$requeued}; jobs failed: {$failed}");

        return self::SUCCESS;
    }
}
