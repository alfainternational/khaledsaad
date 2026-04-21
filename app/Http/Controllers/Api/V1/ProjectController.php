<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpsertProjectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('view', $workspace);

        $rows = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with(['client:id,public_id,name'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Project $p): array => [
                'public_id' => $p->public_id,
                'name' => $p->name,
                'stage' => $p->stage,
                'status' => $p->status,
                'client' => $p->client ? [
                    'public_id' => $p->client->public_id,
                    'name' => $p->client->name,
                ] : null,
            ])->values()->all(),
        ]);
    }

    public function store(UpsertProjectRequest $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('manageProjects', $workspace);

        $data = $request->validated();

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $data['client_id'] ?? null,
            'name' => $data['name'],
            'stage' => $data['stage'],
            'status' => $data['status'],
        ]);

        return response()->json([
            'data' => [
                'public_id' => $project->public_id,
                'name' => $project->name,
                'stage' => $project->stage,
                'status' => $project->status,
            ],
        ], 201);
    }
}
