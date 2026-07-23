<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolRunFile;
use App\Services\Tools\AttachmentUploader;
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

    public function preflight(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->service->preflight($run)]);
    }

    public function queue(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->presenter->progress($this->service->queue($run))], 202);
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
