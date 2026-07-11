<?php

namespace App\Console\Commands;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DisablePrivateWorkerCommand extends Command
{
    protected $signature = 'private-worker:disable {worker_id}';

    protected $description = 'Disable a private worker and release its active leases';

    public function handle(): int
    {
        $worker = IntelligenceWorker::query()->where('public_id', $this->argument('worker_id'))->first();
        if (! $worker) {
            $this->error('Private worker was not found.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($worker): void {
            $worker->update(['status' => 'disabled']);
            IntelligenceJob::query()
                ->where('intelligence_worker_id', $worker->id)
                ->where('status', 'leased')
                ->update([
                    'status' => 'queued',
                    'intelligence_worker_id' => null,
                    'lease_token_hash' => null,
                    'lease_started_at' => null,
                    'leased_until' => null,
                    'progress' => 0,
                    'available_at' => now(),
                ]);
        });

        $this->info('Private worker disabled and active leases released.');

        return self::SUCCESS;
    }
}
