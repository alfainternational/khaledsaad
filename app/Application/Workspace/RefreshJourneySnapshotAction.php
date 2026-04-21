<?php

namespace App\Application\Workspace;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\ReadinessScoreService;
use App\Support\Dashboard\StageCatalog;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;

class RefreshJourneySnapshotAction
{
    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
        private readonly WorkspaceJourneyStore $journeyStore,
        private readonly ReadinessScoreService $readinessScoreService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Project $project): array
    {
        $workspace = $project->workspace()->firstOrFail();
        $profile = $this->profileStore->get($workspace);
        $completedToolCodes = ToolRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->distinct()
            ->pluck('tool_code')
            ->all();

        $stageSequence = collect(StageCatalog::all())
            ->map(function (array $stage, int $number): array {
                return [
                    'number' => $number,
                    'label' => $stage['label'],
                    'tools' => $stage['core_tools'] ?? [],
                ];
            })
            ->all();

        $currentStage = (int) $project->stage;
        $currentStep = null;

        foreach ($stageSequence as $stage) {
            foreach ($stage['tools'] as $toolCode) {
                if (! in_array($toolCode, $completedToolCodes, true)) {
                    $currentStage = (int) $stage['number'];
                    $currentStep = $toolCode;
                    break 2;
                }
            }
        }

        if ($currentStep === null) {
            $currentStep = collect($stageSequence)->last()['tools'][0] ?? null;
        }

        $recommendedTool = $currentStep
            ? Tool::query()->where('code', $currentStep)->first()
            : null;

        $readiness = $this->readinessScoreService->calculate($completedToolCodes);

        $snapshot = [
            'path' => $profile['recommended_path'] ?? PathCatalog::recommend(
                $profile['persona'] ?? null,
                $profile['primary_goal'] ?? null,
                $profile['awareness_level'] ?? null,
            ),
            'current_stage' => $currentStage,
            'current_step' => $currentStep,
            'completed_tools' => $completedToolCodes,
            'completed_count' => count($completedToolCodes),
            'next_tool_name' => $recommendedTool?->name,
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->journeyStore->putProfile($workspace, [
            'recommended_path' => $snapshot['path'],
            'current_stage' => $currentStage,
            'current_step' => $currentStep,
            'completion_snapshot' => [
                'completed_count' => count($completedToolCodes),
                'completed_tools' => $completedToolCodes,
            ],
        ], $project);
        $this->journeyStore->putSnapshot($workspace, $snapshot, $project);
        $this->journeyStore->putReadiness($workspace, $readiness, $project);

        return [
            'snapshot' => $snapshot,
            'readiness' => $readiness,
        ];
    }
}
