<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpsertProjectMarketingBriefRequest;
use App\Support\Projects\ProjectMarketingBriefStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectBriefController extends Controller
{
    use ResolvesCurrentProject;

    public function show(Request $request, ProjectMarketingBriefStore $briefStore): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $project = $this->currentProject();
        $this->authorize('view', $project);

        $brief = $briefStore->get($workspace, $project);

        return response()->json([
            'data' => [
                'brief' => $brief,
                'assessment' => $briefStore->assess($brief),
            ],
        ]);
    }

    public function update(
        UpsertProjectMarketingBriefRequest $request,
        ProjectMarketingBriefStore $briefStore,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $project = $this->currentProject();
        $this->authorize('update', $project);

        $briefStore->put($workspace, $project, $request->validated());
        $brief = $briefStore->get($workspace, $project);

        return response()->json([
            'data' => [
                'brief' => $brief,
                'assessment' => $briefStore->assess($brief),
            ],
        ]);
    }
}
