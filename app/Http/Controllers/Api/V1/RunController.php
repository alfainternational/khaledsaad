<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolRunFile;
use App\Services\Tools\AttachmentUploader;
use App\Services\Tools\HybridInsightService;
use App\Services\Tools\ManualReportService;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RunController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ToolRunService $service,
        private readonly RunPresenter $presenter,
        private readonly AttachmentUploader $uploader,
    ) {}

    public function uploadFile(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        $request->validate(['file' => AttachmentUploader::validationRules()]);
        $this->uploader->store($run, $request->file('file'));

        return response()->json(['data' => $this->presenter->files($run)], 201);
    }

    public function deleteFile(Request $request, ToolRun $run, ToolRunFile $file): JsonResponse
    {
        $this->authorizeRun($request, $run);
        abort_unless($file->tool_run_id === $run->id, 404);

        $this->uploader->delete($file);

        return response()->json(['data' => $this->presenter->files($run)]);
    }

    public function store(Request $request, Project $project, Tool $tool): JsonResponse
    {
        $this->authorizeProject($request, $project);

        try {
            $run = $this->service->start($project, $tool, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->presenter->wizard($run)], 201);
    }

    public function show(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->presenter->wizard($run)]);
    }

    public function saveStep(Request $request, ToolRun $run, int $step): JsonResponse
    {
        $this->authorizeRun($request, $run);

        $this->service->saveStep($run, $step, $request->input('answers', []));

        return response()->json(['data' => $this->presenter->wizard($run)]);
    }

    public function insights(
        Request $request,
        ToolRun $run,
        HybridInsightService $insights,
    ): JsonResponse {
        $this->authorizeRun($request, $run);
        $validated = $request->validate([
            'answers' => 'nullable|array|max:50',
            'include_ai' => 'nullable|boolean',
            'step' => 'nullable|integer|min:1|max:50',
        ]);

        return response()->json(['data' => $insights->preview(
            $run,
            $validated['answers'] ?? [],
            (bool) ($validated['include_ai'] ?? false),
            isset($validated['step']) ? (int) $validated['step'] : null,
        )]);
    }

    public function preflight(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->service->preflight($run)]);
    }

    public function queue(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        try {
            $queued = $this->service->queue($run);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->presenter->progress($queued)], 202);
    }

    public function requestManualReview(
        Request $request,
        ToolRun $run,
        ManualReportService $manual,
    ): JsonResponse {
        $this->authorizeRun($request, $run);

        return response()->json([
            'data' => $this->presenter->progress($manual->requestManualReview($run)),
        ], 202);
    }

    public function progress(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->presenter->progress($run)]);
    }

    public function retry(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->presenter->progress($this->service->retry($run))], 202);
    }

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json([
            'data' => $project->runs()->latest('id')->get()
                ->map(fn (ToolRun $run) => $this->presenter->summary($run))->all(),
        ]);
    }
}
