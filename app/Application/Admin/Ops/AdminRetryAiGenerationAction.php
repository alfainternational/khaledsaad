<?php

namespace App\Application\Admin\Ops;

use App\Application\AI\GenerateTemplateDraftAction;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdminRetryAiGenerationAction
{
    public function __construct(
        private readonly GenerateTemplateDraftAction $generateTemplateDraftAction,
    ) {}

    public function handle(AIGeneration $original, User $adminActor): AIGeneration
    {
        if ($original->ops_review_status === 'voided') {
            throw ValidationException::withMessages([
                'retry' => 'تعذر إعادة التوليد لسجل ملغى تشغيلياً.',
            ]);
        }

        $workspace = Workspace::query()->find($original->workspace_id);
        if ($workspace === null) {
            throw ValidationException::withMessages([
                'retry' => 'مساحة العمل المرتبطة بهذا السجل لم تعد متوفرة.',
            ]);
        }

        $template = AITemplate::query()->find($original->template_id);
        if ($template === null) {
            throw ValidationException::withMessages([
                'retry' => 'القالب المرتبط بهذا السجل لم يعد متوفراً.',
            ]);
        }

        $project = null;
        if ($original->project_id) {
            $project = Project::query()
                ->where('workspace_id', $workspace->id)
                ->findOrFail($original->project_id)
                ->load('client');
        }

        $brief = null;
        if (is_array($original->inputs_json)) {
            $brief = $original->inputs_json['brief'] ?? null;
            $brief = is_string($brief) ? $brief : null;
        }

        return $this->generateTemplateDraftAction->handle(
            workspace: $workspace,
            template: $template,
            project: $project,
            actor: $adminActor,
            brief: $brief,
        );
    }
}
