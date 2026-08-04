<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Modules\Learning\MarketingLearningRecommender;
use App\Services\Tools\ToolEngagement;
use App\Support\Presentation\EngagementPresenter;
use App\Support\Presentation\ProjectPresenter;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProjectPresenter $projects,
        private readonly ToolPresenter $tools,
        private readonly ToolEngagement $engagement,
        private readonly EngagementPresenter $engagements,
        private readonly MarketingLearningRecommender $learning,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('app.dashboard', $this->payload($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $user = $request->user();

        $projects = Project::whereHas('workspace', fn ($query) => $query->where('owner_id', $user->id))
            ->with(['reports', 'tasks'])
            ->latest('id')
            ->get();

        // «أكمل ما بدأته» يتصدر اللوحة: من ترك شيئًا في المنتصف يجب أن يجد
        // طريق العودة أمامه مباشرة، لا أن يبحث عنه.
        $unfinished = $this->engagement->unfinishedFor($user)
            ->map(fn (ToolRun $run) => $this->engagements->resumeCard(
                $run,
                $this->engagement->describe($run),
            ))
            ->values()
            ->all();

        $learningProject = $projects->first();
        $learningNext = null;

        if ($learningProject !== null) {
            $learningRun = MarketingLearningRun::startFor($learningProject, $user);
            $recommendation = $this->learning->next($learningRun);
            $learningNext = [
                ...$recommendation,
                'project' => $learningProject,
            ];
        }

        return [
            'projects' => $projects->map(fn ($project) => $this->projects->card($project))->all(),
            'unfinished' => $unfinished,
            'open_tasks' => $projects->sum(fn ($project) => $project->tasks->where('status', '!=', 'done')->count()),
            'reports_count' => $projects->sum(fn ($project) => $project->reports->count()),
            'suggested_tools' => Tool::runnable()->with('currentVersion')->orderBy('sort_order')->limit(4)->get()
                ->map(fn ($tool) => $this->tools->card($tool))->all(),
            'learningNext' => $learningNext,
        ];
    }
}
