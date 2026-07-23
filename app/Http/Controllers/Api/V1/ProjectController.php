<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use App\Support\Presentation\ProjectPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $projects = Project::whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $projects->map(fn ($project) => $this->presenter->card($project))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(ProjectService::validationRules());
        $project = $this->service->create($request->user(), $data);

        return response()->json(['data' => $this->presenter->overview($project)], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json(['data' => $this->presenter->overview($project)]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate(ProjectService::validationRules(creating: false));

        return response()->json([
            'data' => $this->presenter->overview($this->service->updateProfile($project, $data)),
        ]);
    }
}
