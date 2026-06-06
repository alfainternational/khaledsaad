<?php

namespace App\Jobs;

use App\Domain\Project\Models\Project;
use App\Support\Intelligence\MarketingIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureMonitoringSnapshotJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $projectId,
    ) {}

    public function handle(MarketingIntelligenceService $service): void
    {
        $project = Project::query()->with('workspace')->find($this->projectId);
        if (! $project || ! $project->workspace || ! $project->monitoring_enabled) {
            return;
        }

        $service->run($project, $project->workspace, 'monitoring');
    }
}
