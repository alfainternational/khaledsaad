<?php

namespace App\Support\Intelligence;

use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Intelligence\Models\MonitorSnapshot;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Collection;

class ProjectIntelligenceRepository
{
    public function latestAudit(Project $project): ?AuditRun
    {
        return AuditRun::query()
            ->where('project_id', $project->id)
            ->latest()
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function trend(Project $project, int $limit = 8): array
    {
        return MonitorSnapshot::query()
            ->where('project_id', $project->id)
            ->latest('captured_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (MonitorSnapshot $snapshot): array => [
                'captured_at' => $snapshot->captured_at?->toDateString(),
                'executive_score' => $snapshot->executive_score,
                'website_score' => $snapshot->website_score,
                'social_score' => $snapshot->social_score,
                'seo_score' => $snapshot->seo_score,
                'trust_score' => $snapshot->trust_score,
                'conversion_score' => $snapshot->conversion_score,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, Project>  $projects
     * @return array<int, AuditRun>
     */
    public function latestAuditMap(Workspace $workspace, iterable $projects): array
    {
        $projectIds = collect($projects)
            ->map(fn (Project $project): int => $project->id)
            ->filter()
            ->values();

        if ($projectIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, AuditRun> $runs */
        $runs = AuditRun::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('project_id', $projectIds->all())
            ->latest('id')
            ->get()
            ->unique('project_id')
            ->keyBy('project_id');

        return $runs->all();
    }
}
