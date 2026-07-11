<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Execution\BuildExecutionPackageAction;
use App\Domain\Execution\Models\Recommendation;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ExecutionPackageResource;
use App\Http\Resources\V1\RecommendationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectRecommendationController extends Controller
{
    use ResolvesCurrentProject;

    public function index(Request $request): AnonymousResourceCollection
    {
        $project = $this->currentProject();
        $this->authorize('view', $project);

        $rows = Recommendation::query()
            ->where('project_id', $project->id)
            ->with(['executionPackages.owner', 'executionPackages.tasks.assignee', 'executionPackages.assets', 'executionPackages.reports'])
            ->orderBy('priority')
            ->get();

        return RecommendationResource::collection($rows);
    }

    /**
     * إنشاء (أو إعادة استخدام) حزمة تنفيذ من توصية — idempotent كما في الويب.
     */
    public function storePackage(
        Request $request,
        BuildExecutionPackageAction $action,
    ): JsonResponse {
        $project = $this->currentProject();
        $this->authorize('update', $project);

        $recommendation = Recommendation::query()
            ->where('project_id', $project->id)
            ->where('public_id', (string) $request->route('recommendationPublicId'))
            ->firstOrFail();

        $package = $recommendation->executionPackages()->first()
            ?? $action->handle($recommendation, $request->user());

        return (new ExecutionPackageResource($package->load(['owner', 'tasks.assignee', 'assets', 'reports'])))
            ->response()
            ->setStatusCode(201);
    }
}
