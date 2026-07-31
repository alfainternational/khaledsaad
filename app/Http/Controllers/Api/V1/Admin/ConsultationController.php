<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultationBlueprint;
use App\Models\ConsultationBlueprintVersion;
use App\Models\Project;
use App\Models\QuestionVersion;
use App\Modules\Intake\Catalog\ConsultationCatalogManager;
use App\Modules\Intake\Engine\ModuleScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * محرّر مخططات الاستشارة للآدمن عبر الـAPI — نظير
 * `App\Http\Controllers\Admin\AdminConsultationController`.
 *
 * المسودة تُحرَّر، والإصدار المنشور مقفل: النشر يقفل النسخة ضد التعديل حتى لا
 * تتغيّر أسئلة تحت مستخدمين يجيبون عليها. لا حساب هنا — تحرير كتالوج فقط.
 */
class ConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationCatalogManager $manager,
        private readonly ModuleScopeResolver $scope,
    ) {}

    public function index(): JsonResponse
    {
        $blueprints = ConsultationBlueprint::with([
            'currentVersion',
            'versions' => fn ($q) => $q->latest('version'),
        ])->get();

        return response()->json([
            'data' => $blueprints->map(fn (ConsultationBlueprint $blueprint) => [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'current_version_id' => $blueprint->current_version_id,
                'versions' => $blueprint->versions->map(fn (ConsultationBlueprintVersion $v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'status' => $v->status,
                    'is_current' => $v->id === $blueprint->current_version_id,
                ])->all(),
            ])->all(),
        ]);
    }

    public function show(ConsultationBlueprintVersion $version): JsonResponse
    {
        return response()->json(['data' => $this->serialize($version)]);
    }

    public function createDraft(ConsultationBlueprint $blueprint): JsonResponse
    {
        $draft = $this->manager->createDraft($blueprint);

        return response()->json(['data' => $this->serialize($draft)], 201);
    }

    public function updateQuestion(
        Request $request,
        ConsultationBlueprintVersion $version,
        QuestionVersion $question,
    ): JsonResponse {
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

        return response()->json(['data' => $this->serialize($version->fresh())]);
    }

    public function publish(ConsultationBlueprintVersion $version): JsonResponse
    {
        $published = $this->manager->publish($version);

        return response()->json(['data' => $this->serialize($published)]);
    }

    public function simulate(Request $request, ConsultationBlueprintVersion $version): JsonResponse
    {
        $validated = $request->validate(['project' => 'required|string']);

        $project = Project::where('slug', $validated['project'])->firstOrFail();
        $version->load('blueprint', 'modules.module', 'modules.questions.questionVersion.definition');

        $result = $version->modules->map(fn ($binding) => [
            'module' => $binding->module->name,
            ...$this->scope->resolve($binding, $project),
        ])->all();

        return response()->json(['data' => [
            'project' => ['slug' => $project->slug, 'name' => $project->name],
            'result' => $result,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ConsultationBlueprintVersion $version): array
    {
        $version->load('blueprint', 'modules.module', 'modules.questions.questionVersion.definition');

        return [
            'id' => $version->id,
            'version' => $version->version,
            'status' => $version->status,
            'is_editable' => $version->status === 'draft',
            'blueprint' => [
                'id' => $version->blueprint->id,
                'name' => $version->blueprint->name,
            ],
            'modules' => $version->modules->map(fn ($binding) => [
                'name' => $binding->module->name,
                'importance' => $binding->importance,
                'questions' => $binding->questions->map(function ($q) {
                    $qv = $q->questionVersion;

                    return [
                        'id' => $qv->id,
                        'key' => $qv->definition->key,
                        'answer_type' => $qv->answer_type,
                        'diagnostic_impact' => $q->diagnostic_impact,
                        'user_text' => $qv->user_text,
                        'help_text' => $qv->help_text,
                        'why_text' => $qv->why_text,
                        'required' => (bool) $qv->required,
                        'allow_unknown' => (bool) $qv->allow_unknown,
                        'allow_skip' => (bool) $qv->allow_skip,
                        'options' => $qv->options ?? [],
                    ];
                })->all(),
            ])->all(),
        ];
    }
}
