<?php

namespace App\Application\Execution;

use App\Domain\Execution\Models\Recommendation;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns the trusted findings of a completed audit into prioritised, evidence-backed
 * recommendations (Phase ج). Idempotent per finding: re-running won't duplicate.
 */
class GenerateRecommendationsFromAuditAction
{
    public function handle(Project $project, AuditRun $auditRun, ?User $actor = null, int $limit = 6): Collection
    {
        $findings = $auditRun->findings()
            ->orderByDesc('score_impact')
            ->get()
            ->filter(fn ($finding): bool => (float) ($finding->confidence ?? 0) >= 0.5)
            ->take($limit)
            ->values();

        $created = collect();
        $rank = 1;

        foreach ($findings as $finding) {
            $existing = Recommendation::query()
                ->where('audit_finding_id', $finding->id)
                ->first();

            if ($existing !== null) {
                $created->push($existing);
                $rank++;

                continue;
            }

            $created->push(Recommendation::query()->create([
                'public_id' => (string) Str::ulid(),
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'audit_finding_id' => $finding->id,
                'area' => $finding->area,
                'title' => $finding->title,
                'priority' => $rank * 10,
                'severity' => $finding->severity ?: 'medium',
                'evidence' => $finding->evidence,
                'rationale' => $finding->recommendation,
                'estimated_impact' => $this->impactFromScore((int) $finding->score_impact),
                'confidence' => (float) $finding->confidence,
                'status' => 'proposed',
                'created_by' => $actor?->id,
            ]));

            $rank++;
        }

        return $created;
    }

    private function impactFromScore(int $scoreImpact): string
    {
        return match (true) {
            $scoreImpact >= 18 => 'high',
            $scoreImpact >= 10 => 'medium',
            default => 'low',
        };
    }
}
