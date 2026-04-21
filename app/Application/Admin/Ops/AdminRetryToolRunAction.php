<?php

namespace App\Application\Admin\Ops;

use App\Application\Tooling\RunToolAction;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdminRetryToolRunAction
{
    public function __construct(
        private readonly RunToolAction $runToolAction,
    ) {}

    public function handle(ToolRun $original, User $adminActor): ToolRun
    {
        if ($original->ops_review_status === 'voided') {
            throw ValidationException::withMessages([
                'retry' => 'تعذر إعادة تشغيل سجل ملغى تشغيلياً.',
            ]);
        }

        $workspace = Workspace::query()->findOrFail($original->workspace_id);
        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($original->project_id);

        $tool = Tool::query()->where('code', $original->tool_code)->firstOrFail();

        $inputs = is_array($original->inputs_json) ? $original->inputs_json : [];

        return $this->runToolAction->handle(
            $workspace,
            $project,
            $tool,
            $adminActor,
            (string) $original->mode,
            $inputs,
        );
    }
}
