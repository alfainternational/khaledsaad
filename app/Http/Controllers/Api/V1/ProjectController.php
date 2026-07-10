<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpsertProjectRequest;
use App\Http\Resources\V1\ProjectDetailResource;
use App\Http\Resources\V1\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('view', $workspace);

        $rows = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with(['client:id,public_id,name'])
            ->when(
                $request->string('status')->isNotEmpty(),
                fn ($query) => $query->where('status', $request->string('status')->value())
            )
            ->when(
                $request->integer('stage') > 0,
                fn ($query) => $query->where('stage', $request->integer('stage'))
            )
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 50));

        return ProjectResource::collection($rows);
    }

    public function store(UpsertProjectRequest $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('manageProjects', $workspace);

        $project = Project::query()->create(
            $this->attributesFrom($request) + ['workspace_id' => $workspace->id]
        );

        return (new ProjectResource($project->load('client')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request): ProjectDetailResource
    {
        $project = $this->resolveProject();
        $this->authorize('view', $project);

        return new ProjectDetailResource(
            $project->load('client')->loadCount(['toolRuns', 'approvals'])
        );
    }

    public function update(UpsertProjectRequest $request): ProjectDetailResource
    {
        $project = $this->resolveProject();
        $this->authorize('update', $project);

        $project->update($this->attributesFrom($request));

        return new ProjectDetailResource($project->load('client'));
    }

    public function destroy(Request $request): Response
    {
        $project = $this->resolveProject();
        $this->authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }

    /**
     * يحل المشروع من project_id الذي يحقنه middleware api.project.
     */
    private function resolveProject(): Project
    {
        return Project::query()->findOrFail(request()->input('project_id'));
    }

    /**
     * الحقول الكاملة للمشروع من الطلب المُتحقَّق (مطابقة لسلوك الويب).
     *
     * @return array<string, mixed>
     */
    private function attributesFrom(UpsertProjectRequest $request): array
    {
        $data = $request->validated();

        return [
            'client_id' => $data['client_id'] ?? null,
            'name' => $data['name'],
            'stage' => $data['stage'],
            'status' => $data['status'],
            'sector' => $data['sector'],
            'market_country' => $data['market_country'] ?? null,
            'primary_domain' => $data['primary_domain'] ?? null,
            'official_social_links_json' => $data['official_social_links_json'] ?? [],
            'verified_social_profiles_json' => $data['verified_social_profiles_json'] ?? [],
            'competitors_json' => $data['competitors_json'] ?? [],
            'analysis_goals_json' => $data['analysis_goals_json'] ?? [],
            'monitoring_enabled' => $data['monitoring_enabled'] ?? false,
        ];
    }
}
