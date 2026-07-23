<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tool;
use App\Services\Tools\ToolEngagement;
use App\Support\Presentation\EngagementPresenter;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToolCatalogController extends Controller
{
    public function __construct(
        private readonly ToolPresenter $presenter,
        private readonly ToolEngagement $engagement,
        private readonly EngagementPresenter $engagements,
    ) {}

    public function index(Request $request): View
    {
        return view('app.tools.index', [
            'tools' => $this->catalog(),
            'engagements' => $this->engagementMap($request),
            'projects' => $this->projects($request),
        ]);
    }

    public function show(Request $request, Tool $tool): View
    {
        return view('app.tools.show', [
            'tool' => $this->presenter->detail($tool->load(['currentVersion.fields'])),
            'engagement' => $this->engagements->decorate(
                $this->engagement->forUser($request->user(), $tool),
                $tool->key,
            ),
            'projects' => $this->projects($request),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return Tool::with('currentVersion')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Tool $tool) => $this->presenter->card($tool))
            ->all();
    }

    /**
     * حالة كل أداة بالنسبة لهذا المستخدم، مفتاحها مفتاح الأداة.
     *
     * @return array<string, array<string, mixed>>
     */
    private function engagementMap(Request $request): array
    {
        $tools = Tool::with('currentVersion')->orderBy('sort_order')->get();

        return collect($this->engagement->mapForUser($request->user(), $tools))
            ->map(fn (array $state, string $key) => $this->engagements->decorate($state, $key))
            ->all();
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    private function projects(Request $request): array
    {
        return Project::whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn ($project) => ['slug' => $project->slug, 'name' => $project->name])
            ->all();
    }
}
