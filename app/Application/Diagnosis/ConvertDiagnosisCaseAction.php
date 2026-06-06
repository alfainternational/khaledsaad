<?php

namespace App\Application\Diagnosis;

use App\Domain\Intelligence\Models\DiagnosisCase;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Intelligence\MarketingIntelligenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Converts a pre-registration DiagnosisCase into a real Workspace Project on signup,
 * so the new user continues from their diagnosis instead of starting from scratch
 * (Phase أ: "بعد التسجيل لا نعيد المستخدم من البداية").
 */
class ConvertDiagnosisCaseAction
{
    public function __construct(
        private readonly MarketingIntelligenceService $intelligence,
    ) {}

    public function handle(DiagnosisCase $case, User $user, Workspace $workspace): ?Project
    {
        if ($case->status === 'converted' && $case->converted_project_id) {
            return Project::query()->find($case->converted_project_id);
        }

        if ($case->isExpired()) {
            return null;
        }

        return DB::transaction(function () use ($case, $workspace): Project {
            $project = Project::query()->create([
                'public_id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'client_id' => null,
                'name' => $this->projectName($case),
                'stage' => 1,
                'status' => 'active',
                'sector' => $case->sector ?: 'general',
                'primary_domain' => $case->input_url,
                'competitors_json' => $this->competitors($case),
                'analysis_goals_json' => $case->goal ? [$case->goal] : [],
                'monitoring_enabled' => false,
            ]);

            $case->update([
                'status' => 'converted',
                'converted_workspace_id' => $workspace->id,
                'converted_project_id' => $project->id,
            ]);

            // Re-run a full, persisted audit for the new project (queued, in-house engine).
            if (filled($project->primary_domain)) {
                $this->intelligence->queue($project, $workspace, 'diagnosis_conversion');
            }

            return $project;
        });
    }

    private function projectName(DiagnosisCase $case): string
    {
        if (filled($case->business_name)) {
            return (string) $case->business_name;
        }

        if (filled($case->input_url)) {
            $host = parse_url((string) $case->input_url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return 'مشروعي الأول';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function competitors(DiagnosisCase $case): array
    {
        return collect($case->competitors_json ?? [])
            ->map(function ($competitor): ?array {
                $value = is_array($competitor) ? ($competitor['domain'] ?? $competitor['label'] ?? null) : $competitor;
                $value = is_string($value) ? trim($value) : '';

                return $value !== '' ? ['label' => $value, 'domain' => $value] : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
