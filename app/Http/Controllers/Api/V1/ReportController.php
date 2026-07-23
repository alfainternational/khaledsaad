<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Report;
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

        return response()->json([
            'data' => $this->presenter->full($report),
            'comparison' => $this->presenter->comparison($report, $this->previous($report)),
        ]);
    }

    public function convert(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);

        $recommendationId = $request->integer('recommendation_id');

        $tasks = $recommendationId > 0
            ? [$this->runs->convertRecommendation(
                Recommendation::where('report_id', $report->id)->findOrFail($recommendationId),
                $request->user(),
            )]
            : $this->runs->convertTopRecommendations($report, $request->user());

        return response()->json([
            'data' => array_map(fn ($task) => $this->projects->task($task), $tasks),
        ], 201);
    }

    private function previous(Report $report): ?Report
    {
        return Report::where('project_id', $report->project_id)
            ->where('id', '!=', $report->id)
            ->whereHas('toolRun', fn ($query) => $query->where('tool_version_id', $report->toolRun->tool_version_id))
            ->where('created_at', '<', $report->created_at)
            ->latest('created_at')
            ->first();
    }
}
