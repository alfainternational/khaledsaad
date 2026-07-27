<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\CarriesStartIntent;
use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolField;
use App\Models\ToolVersion;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ProjectAnswerMemory;
use App\Services\Tools\ToolEngagement;
use App\Services\Tools\ToolRunService;
use App\Support\Kpis\KpiTemplates;
use App\Support\Presentation\EngagementPresenter;
use App\Support\Presentation\ProjectPresenter;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ProjectController extends Controller
{
    use CarriesStartIntent;
    use ResolvesWorkspace;

    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectPresenter $presenter,
        private readonly ToolPresenter $tools,
        private readonly ToolRunService $runs,
        private readonly ProjectAnswerMemory $memory,
        private readonly ToolEngagement $engagement,
        private readonly EngagementPresenter $engagements,
    ) {}

    public function index(Request $request): View
    {
        return view('app.projects.index', [
            'projects' => $this->userProjects($request)->map(fn ($project) => $this->presenter->card($project))->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $tool = $this->rememberStartIntent($request);

        return view('app.projects.create', [
            'startTool' => $tool !== null ? $this->tools->card($tool) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(ProjectService::validationRules());
        $project = $this->service->create($request->user(), $data);

        // إن كان المستخدم قد بدأ رحلته من أداة، تُفتح الأداة مباشرة على مشروعه
        // الجديد: خطوة واحدة بدل ثلاث نقرات إضافية.
        $tool = $this->consumeStartIntent($request);

        if ($tool !== null) {
            try {
                $run = $this->runs->start($project, $tool, $request->user());

                return redirect()->route('app.runs.step', [$run, 1])
                    ->with('status', "أُنشئ المشروع. لنبدأ بـ«{$tool->title}».");
            } catch (RuntimeException) {
                // الأداة تعطلت بين الاختيار والإنشاء: نكمل إلى المشروع بدل رمي خطأ.
            }
        }

        return redirect()->route('app.projects.show', $project)
            ->with('status', 'أُضيف المشروع. اختر التشخيص الذي يناسب أولويتك الحالية.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        $tools = Tool::with('currentVersion')->orderBy('sort_order')->get();

        return view('app.projects.show', [
            'project' => $this->presenter->overview($project),
            'tools' => $tools->map(fn ($tool) => $this->tools->card($tool))->all(),
            // مؤشرات نموذجية يفهم منها المستخدم المقصود ويبدأ بضغطة.
            'kpiTemplates' => KpiTemplates::catalog(),
            // حالة كل أداة داخل هذا المشروع تحديدًا، لا عبر مشاريعه كلها.
            'engagements' => $tools
                ->mapWithKeys(fn (Tool $tool) => [
                    $tool->key => $this->engagements->decorate(
                        $this->engagement->forProject($project, $tool),
                        $tool->key,
                    ),
                ])
                ->all(),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        // كل ما كتبه المستخدم داخل الخطوات يظهر هنا، فلا يضيع داخل أداة واحدة.
        $fields = ToolField::whereIn('tool_version_id', ToolVersion::query()->select('id'))
            ->orderBy('sort_order')
            ->get()
            ->unique('key');

        return view('app.projects.edit', [
            'project' => $project->load('profile'),
            'known' => $this->memory->knownFor($project, $fields),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate(ProjectService::validationRules(creating: false));
        $this->service->updateProfile($project, $data);

        return redirect()->route('app.projects.show', $project)
            ->with('status', 'حُدّث ملف المشروع. التقارير السابقة لم تتغير.');
    }

    /**
     * @return Collection<int, Project>
     */
    private function userProjects(Request $request)
    {
        return Project::whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->latest('id')
            ->get();
    }
}
