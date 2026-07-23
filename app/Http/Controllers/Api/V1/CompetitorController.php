<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCompetitor;
use App\Services\Competitors\CompetitorRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نظير التطبيق لإدارة المنافسين — نفس CompetitorRegistry التي يستدعيها الويب.
 */
class CompetitorController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(private readonly CompetitorRegistry $registry) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json(['data' => $this->registry->forReport($project)]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate(['names' => 'required|string|max:500']);
        $this->registry->rememberNamed($project, $data['names']);

        return response()->json(['data' => $this->registry->forReport($project->refresh())], 201);
    }

    public function confirm(Request $request, ProjectCompetitor $competitor): JsonResponse
    {
        $this->authorizeProject($request, $competitor->project);
        $this->registry->confirm($competitor);

        return response()->json(['data' => $this->registry->forReport($competitor->project)]);
    }

    public function dismiss(Request $request, ProjectCompetitor $competitor): JsonResponse
    {
        $this->authorizeProject($request, $competitor->project);
        $this->registry->dismiss($competitor);

        return response()->json(['data' => $this->registry->forReport($competitor->project)]);
    }
}
