<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Services\Tools\ToolEngagement;
use App\Support\Presentation\EngagementPresenter;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نظير التطبيق لما يراه الويب: أي أداة بدأها المستخدم، وأين وقف.
 * بدون هذا كان التطبيق سيعرض «ابدأ من هنا» لعمل قائم، تمامًا كما كان الويب.
 */
class EngagementController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ToolEngagement $engagement,
        private readonly EngagementPresenter $engagements,
        private readonly ToolPresenter $tools,
    ) {}

    /**
     * كتالوج الأدوات مع حالة كل أداة داخل المشروع.
     */
    public function projectTools(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $tools = Tool::with('currentVersion')->orderBy('sort_order')->get();

        return response()->json([
            'data' => $tools->map(fn (Tool $tool) => [
                ...$this->tools->card($tool),
                'engagement' => $this->engagements->decorate(
                    $this->engagement->forProject($project, $tool),
                    $tool->key,
                ),
            ])->all(),
        ]);
    }

    /**
     * كل ما بدأه المستخدم ولم يكمله — نظير «أكمل ما بدأته» في اللوحة.
     */
    public function unfinished(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->engagement->unfinishedFor($request->user())
                ->map(fn (ToolRun $run) => $this->engagements->resumeCard(
                    $run,
                    $this->engagement->describe($run),
                ))
                ->values()
                ->all(),
        ]);
    }
}
