<?php

namespace App\Jobs;

use App\Application\Execution\GenerateRecommendationsFromAuditAction;
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

        $auditRun = $service->execute($auditRun);

        // Phase ج: a completed audit immediately yields prioritised recommendations,
        // so the diagnosis is never just a report — it is ready to execute.
        if ($auditRun->status === 'completed') {
            $project = $auditRun->project()->first();
            if ($project) {
                app(GenerateRecommendationsFromAuditAction::class)->handle($project, $auditRun);
            }
        }
    }
}
