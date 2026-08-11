<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultationBlueprint;
use App\Models\ConsultationBlueprintVersion;
use App\Models\Project;
use App\Models\QuestionVersion;
use App\Modules\Intake\Catalog\ConsultationCatalogManager;
use App\Modules\Intake\Catalog\ConsultationCatalogValidator;
use App\Modules\Intake\Engine\ModuleScopeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationCatalogManager $manager,
        private readonly ConsultationCatalogValidator $validator,
        private readonly ModuleScopeResolver $scope,
    ) {}

    public function index(): View
    {
        return view('admin.consultations.index', [
            'blueprints' => ConsultationBlueprint::with(['currentVersion', 'versions' => fn ($q) => $q->latest('version')])->get(),
        ]);
    }

    public function show(ConsultationBlueprintVersion $version): View
    {
        $version->load('blueprint', 'modules.module', 'modules.questions.questionVersion.definition');

        return view('admin.consultations.show', compact('version'));
    }

    public function createDraft(ConsultationBlueprint $blueprint): RedirectResponse
    {
        $draft = $this->manager->createDraft($blueprint);

        return redirect()->route('admin.consultations.show', $draft)->with('status', __('أُنشئت مسودة مستقلة؛ الإصدار المنشور لم يتغير.'));
    }

    public function updateQuestion(Request $request, ConsultationBlueprintVersion $version, QuestionVersion $question): RedirectResponse
    {
        $validated = $request->validate([
            'user_text' => 'required|string|min:3|max:1000',
            'help_text' => 'nullable|string|max:2000',
            'why_text' => 'nullable|string|max:2000',
            'required' => 'nullable|boolean',
            'allow_unknown' => 'nullable|boolean',
            'allow_skip' => 'nullable|boolean',
        ]);
        $validated['required'] = $request->boolean('required');
        $validated['allow_unknown'] = $request->boolean('allow_unknown');
        $validated['allow_skip'] = $request->boolean('allow_skip');
        $this->manager->updateQuestion($version, $question, $validated);

        return back()->with('status', __('حُفظ السؤال في المسودة.'));
    }

    public function publish(ConsultationBlueprintVersion $version): RedirectResponse
    {
        $published = $this->manager->publish($version);

        return redirect()->route('admin.consultations.show', $published)->with('status', __('نُشر الإصدار وقُفل ضد التعديل.'));
    }

    public function simulate(Request $request, ConsultationBlueprintVersion $version): View
    {
        $version->load('blueprint', 'modules.module', 'modules.questions.questionVersion.definition');
        $project = null;
        $result = [];
        if ($request->filled('project')) {
            $project = Project::where('slug', $request->string('project'))->firstOrFail();
            $result = $version->modules->map(function ($binding) use ($project) {
                return ['module' => $binding->module->name, ...$this->scope->resolve($binding, $project)];
            })->all();
        }

        return view('admin.consultations.simulate', compact('version', 'project', 'result'));
    }
}
