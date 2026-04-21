<?php

namespace App\Application\Tooling;

use App\Application\Workspace\RefreshJourneySnapshotAction;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;

class RunToolAction
{
    public function __construct(
        private readonly BuildToolPayloadAction $buildToolPayloadAction,
        private readonly RefreshJourneySnapshotAction $refreshJourneySnapshotAction,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function handle(
        Workspace $workspace,
        Project $project,
        Tool $tool,
        User $actor,
        string $mode = 'guided',
        array $inputs = [],
    ): ToolRun {
        $payload = $this->buildToolPayloadAction->handle($workspace, $project, $tool, $mode, $inputs);

        $run = ToolRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'tool_code' => $tool->code,
            'mode' => $mode,
            'inputs_json' => $inputs,
            'output_json' => $payload['output'],
            'summary_json' => $payload['summary'],
            'next_actions_json' => $payload['next_actions'],
            'source_context_json' => $payload['source_context'],
            'completeness_score' => $payload['completeness_score'],
            'created_by' => $actor->id,
        ]);

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => 'tools.'.$tool->code,
            ],
            [
                'value_json' => [
                    'tool_code' => $tool->code,
                    'tool_name' => $tool->name ?: $tool->code,
                    'mode' => $mode,
                    'summary' => $payload['summary']['text'],
                    'headline' => $payload['summary']['headline'],
                    'last_run_id' => $run->id,
                    'last_run_at' => now()->toDateTimeString(),
                    'completeness_score' => $payload['completeness_score'],
                ],
            ],
        );

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => 'tool.summary.'.$tool->code,
            ],
            [
                'value_json' => $payload['summary'],
            ],
        );

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => 'tool.context.'.$tool->code,
            ],
            [
                'value_json' => $payload['source_context'],
            ],
        );

        $this->refreshJourneySnapshotAction->handle($project);

        return $run;
    }
}
