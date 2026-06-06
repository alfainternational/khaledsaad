<?php

namespace App\Http\Controllers\Web;

use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\UpsertProjectMarketingBriefRequest;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectMarketingBriefController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function edit(
        Request $request,
        Project $project,
        ProjectMarketingBriefStore $briefStore,
    ): View {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('update', $project);

        $brief = $briefStore->get($workspace, $project);
        $assessment = $briefStore->assess($brief);

        return view('app.projects.brief', [
            'workspace' => $workspace,
            'project' => $project->loadMissing('client'),
            'brief' => $brief,
            'briefAssessment' => $assessment,
        ]);
    }

    public function update(
        UpsertProjectMarketingBriefRequest $request,
        Project $project,
        ProjectMarketingBriefStore $briefStore,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('update', $project);

        $briefStore->put($workspace, $project, $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', $flash->updated('ملف المشروع التسويقي'));
    }
}
