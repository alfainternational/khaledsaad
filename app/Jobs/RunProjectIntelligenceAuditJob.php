<?php

namespace App\Jobs;

use App\Domain\Intelligence\Models\AuditRun;
use App\Support\Intelligence\MarketingIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunProjectIntelligenceAuditJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $auditRunId,
    ) {}

    public function handle(MarketingIntelligenceService $service): void
    {
        $auditRun = AuditRun::query()->find($this->auditRunId);
        if (! $auditRun) {
            return;
        }

        $service->execute($auditRun);
    }
}
