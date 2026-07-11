<?php

namespace App\Http\Resources\V1;

use App\Domain\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * النسخة التفصيلية لمشروع واحد — تضيف الحقول الغنية وسياق الرحلة والتدقيق.
 * الحقول الإضافية (brief_assessment/journey_snapshot/readiness/latest_audit)
 * تُحقن من الـ controller كسمات مُحسوبة عند توفّرها.
 *
 * @mixin Project
 */
class ProjectDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'stage' => $this->stage,
            'status' => $this->status,
            'sector' => $this->sector,
            'market_country' => $this->market_country,
            'primary_domain' => $this->primary_domain,
            'monitoring_enabled' => (bool) $this->monitoring_enabled,
            'official_social_links' => $this->official_social_links_json ?? [],
            'verified_social_profiles' => $this->verified_social_profiles_json ?? [],
            'competitors' => $this->competitors_json ?? [],
            'analysis_goals' => $this->analysis_goals_json ?? [],
            'client' => new ClientResource($this->whenLoaded('client')),
            'brief_assessment' => $this->whenHas('brief_assessment'),
            'journey_snapshot' => $this->whenHas('journey_snapshot'),
            'readiness' => $this->whenHas('readiness'),
            'latest_audit' => $this->whenHas('latest_audit'),
            'execution_summary' => $this->whenLoaded('executionPackages', fn () => $this->executionSummary()),
            'recent_execution_packages' => $this->whenLoaded('executionPackages', fn () => $this->recentExecutionPackages()),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function executionSummary(): array
    {
        $packages = $this->executionPackages;
        $activeStatuses = ['approved', 'in_progress', 'executed', 'measuring'];
        $latestReport = $packages
            ->flatMap(fn ($package) => $package->relationLoaded('reports') ? $package->reports : collect())
            ->sortByDesc('id')
            ->first();

        $totalTasks = $packages->sum(fn ($package) => $package->relationLoaded('tasks') ? $package->tasks->count() : 0);
        $doneTasks = $packages->sum(fn ($package) => $package->relationLoaded('tasks') ? $package->tasks->where('status', 'done')->count() : 0);
        $metric = $latestReport ? collect($latestReport->metrics_json ?? [])->first() : null;

        return [
            'packages_count' => $packages->count(),
            'active_packages_count' => $packages->whereIn('status', $activeStatuses)->count(),
            'total_tasks' => $totalTasks,
            'done_tasks' => $doneTasks,
            'task_progress_percent' => $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0,
            'latest_measurement' => $latestReport ? [
                'phase' => $latestReport->phase,
                'phase_label' => $this->reportPhaseLabel((string) $latestReport->phase),
                'progress' => $latestReport->progress,
                'metric' => $metric ?: null,
                'note' => $latestReport->notes_json['summary'] ?? null,
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentExecutionPackages(): array
    {
        return $this->executionPackages->map(function ($package): array {
            $totalTasks = $package->relationLoaded('tasks') ? $package->tasks->count() : 0;
            $doneTasks = $package->relationLoaded('tasks') ? $package->tasks->where('status', 'done')->count() : 0;
            $latestReport = $package->relationLoaded('reports') ? $package->reports->first() : null;

            return [
                'public_id' => $package->public_id,
                'title' => $package->title,
                'status' => $package->status,
                'status_label' => $this->packageStatusLabel((string) $package->status),
                'total_tasks' => $totalTasks,
                'done_tasks' => $doneTasks,
                'task_progress_percent' => $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0,
                'latest_measurement' => $latestReport ? [
                    'phase' => $latestReport->phase,
                    'phase_label' => $this->reportPhaseLabel((string) $latestReport->phase),
                    'progress' => $latestReport->progress,
                ] : null,
            ];
        })->values()->all();
    }

    private function packageStatusLabel(string $status): string
    {
        return match ($status) {
            'proposed' => 'مقترحة',
            'in_review' => 'قيد المراجعة',
            'approved' => 'معتمدة',
            'in_progress' => 'قيد التنفيذ',
            'executed' => 'منفّذة',
            'measuring' => 'تحت القياس',
            default => $status,
        };
    }

    private function reportPhaseLabel(string $phase): string
    {
        return match ($phase) {
            'discovery' => 'اكتشاف',
            'planning' => 'تخطيط',
            'execution' => 'تنفيذ',
            'validation' => 'تحقق',
            default => $phase,
        };
    }
}
