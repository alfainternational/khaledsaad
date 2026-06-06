<?php

namespace App\Http\Controllers\Web;

use App\Application\Execution\BuildExecutionPackageAction;
use App\Domain\Execution\Models\Recommendation;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function index(Request $request, Project $project): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('view', $project);
        abort_unless($project->workspace_id === $workspace->id, 404);

        $recommendations = Recommendation::query()
            ->where('project_id', $project->id)
            ->with('executionPackages')
            ->orderBy('priority')
            ->get();

        return view('app.recommendations.index', [
            'project' => $project,
            'recommendations' => $recommendations,
        ]);
    }

    public function storePackage(
        Request $request,
        Project $project,
        Recommendation $recommendation,
        BuildExecutionPackageAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('update', $project);
        abort_unless(
            $project->workspace_id === $workspace->id && $recommendation->project_id === $project->id,
            404,
        );

        // Idempotent: re-use an existing package for this recommendation.
        $package = $recommendation->executionPackages()->first()
            ?? $action->handle($recommendation, $request->user());

        return redirect()
            ->route('execution-packages.show', $package)
            ->with('status', $flash->created('حزمة التنفيذ'));
    }
}
