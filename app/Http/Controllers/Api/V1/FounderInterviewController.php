<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Interview\RunFounderInterviewAction;
use App\Domain\AI\Speech\SpeechToTextContract;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Interview\FounderInterviewCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مقابلة المؤسِّس عبر API v1 (تطابق الموبايل مع الويب — المرحلة 4).
 * تحفظ الإجابات كقيم canonical فتُلقّم أدوات الموبايل تلقائياً (نفس حلقة الويب).
 */
class FounderInterviewController extends Controller
{
    use ResolvesCurrentProject;

    public function show(Request $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $project = $this->currentProject();
        $this->authorize('view', $project);

        $existing = WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->whereIn('key', FounderInterviewCatalog::keys())
            ->get()
            ->mapWithKeys(fn (WorkspaceData $row): array => [
                $row->key => (string) ($row->value_json['value'] ?? ''),
            ])
            ->all();

        return response()->json([
            'data' => [
                'questions' => FounderInterviewCatalog::questions(),
                'answers' => $existing,
                'voice_enabled' => app(SpeechToTextContract::class)->isAvailable(),
            ],
        ]);
    }

    public function store(Request $request, RunFounderInterviewAction $action): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $project = $this->currentProject();
        $this->authorize('update', $project);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $action->handle($workspace, $project, $request->user(), $validated['answers']);

        return response()->json([
            'data' => [
                'saved' => $result['saved'],
                'count' => $result['count'],
            ],
        ], $result['count'] > 0 ? 200 : 422);
    }
}
