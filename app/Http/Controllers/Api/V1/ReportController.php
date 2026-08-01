<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Report;
use App\Services\Growth\NextToolSuggester;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\ProjectPresenter;
use App\Support\Presentation\ReportPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ReportPresenter $presenter,
        private readonly ProjectPresenter $projects,
        private readonly ToolRunService $runs,
        private readonly NextToolSuggester $suggester,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json([
            'data' => $project->reports()->latest('created_at')->get()
                ->map(fn (Report $report) => $this->presenter->card($report))->all(),
        ]);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);
        $watcher = $report->watcher;
        $suggestion = $this->suggester->suggest($report->project);

        return response()->json([
            'data' => $this->presenter->full($report),
            'comparison' => $this->presenter->comparison($report, $this->presenter->previousFor($report)),
            'watcher' => $watcher ? [
                'status' => $watcher->status,
                'last_checked_at' => $watcher->last_checked_at?->toIso8601String(),
                'last_changed_at' => $watcher->last_changed_at?->toIso8601String(),
                'changes' => $watcher->changes ?? [],
            ] : null,
            'my_verdict' => $report->feedback()
                ->where('user_id', $request->user()->id)
                ->value('verdict'),
            'suggestion' => $suggestion ? [
                'tool' => [
                    'key' => $suggestion['tool']->key,
                    'title' => $suggestion['tool']->title,
                ],
                'reason' => $suggestion['reason'],
            ] : null,
        ]);
    }

    public function convert(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);

        $recommendationId = $request->integer('recommendation_id');

        // نفس العقد لا إصدارًا ثانيًا (§١٤): scope حقل اختياري، وغيابه يبقي
        // سلوك النسخ المنشورة من التطبيق كما هو.
        $tasks = match (true) {
            $recommendationId > 0 => [$this->runs->convertRecommendation(
                Recommendation::where('report_id', $report->id)->findOrFail($recommendationId),
                $request->user(),
            )],
            $request->input('scope') === 'all' => $this->runs->convertAllRecommendations($report, $request->user()),
            default => $this->runs->convertTopRecommendations($report, $request->user()),
        };

        return response()->json([
            'data' => array_map(fn ($task) => $this->projects->task($task), $tasks),
        ], 201);
    }
}
