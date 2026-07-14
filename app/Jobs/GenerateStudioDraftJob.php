<?php

namespace App\Jobs;

use App\Application\AI\GenerateTemplateDraftAction;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a studio draft generation off the request cycle (queue) so the user is not
 * blocked for ~46s. Updates the pre-created placeholder AIGeneration in place.
 *
 * Retries reuse the SAME placeholder (persist updates the target) so a mid-run
 * worker restart never produces a duplicate record.
 */
class GenerateStudioDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 170;

    public function __construct(
        public int $generationId,
        public int $workspaceId,
        public int $templateId,
        public ?int $projectId,
        public int $actorId,
        public ?string $brief,
    ) {}

    public function handle(GenerateTemplateDraftAction $action): void
    {
        $generation = AIGeneration::query()->find($this->generationId);
        if ($generation === null) {
            return;
        }

        $workspace = Workspace::query()->find($this->workspaceId);
        $template = AITemplate::query()->find($this->templateId);
        $actor = User::query()->find($this->actorId);
        $project = $this->projectId
            ? Project::query()->where('workspace_id', $this->workspaceId)->with('client')->find($this->projectId)
            : null;

        if ($workspace === null || $template === null || $actor === null) {
            $generation->update(['status' => 'failed', 'error' => 'تعذّر تحميل سياق التوليد.']);

            return;
        }

        $generation->update(['status' => 'processing']);

        $action->handle(
            workspace: $workspace,
            template: $template,
            project: $project,
            actor: $actor,
            brief: $this->brief,
            target: $generation,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Studio draft job failed: '.$exception->getMessage(), ['generation' => $this->generationId]);

        AIGeneration::query()->whereKey($this->generationId)->update([
            'status' => 'failed',
            'error' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}
