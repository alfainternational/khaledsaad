<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageRecord;
use App\Models\PromptVersion;
use App\Models\Tool;
use App\Models\ToolVersion;
use App\Services\Tools\PipelineSchemas;
use App\Services\Tools\ToolBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * إدارة الأدوات بنظام CRUD كامل من الواجهة.
 *
 * البيانات البنيوية (الحقول، المخطط، قواعد الدرجة) تُحرَّر كـJSON مُتحقَّق منه،
 * فيستطيع الآدمن تعديل كل شيء دون لمس ملف كود واحد. البرومبتات تُحرَّر نصًّا
 * مع احترام قفل BR-012.
 */
class AdminToolController extends Controller
{
    public function __construct(private readonly ToolBuilder $builder) {}

    public function index(): View
    {
        $costByTool = AiUsageRecord::query()
            ->join('tool_runs', 'ai_usage_records.tool_run_id', '=', 'tool_runs.id')
            ->join('tool_versions', 'tool_runs.tool_version_id', '=', 'tool_versions.id')
            ->selectRaw('tool_versions.tool_id, sum(ai_usage_records.cost_usd) as cost, count(*) as calls')
            ->groupBy('tool_versions.tool_id')
            ->get()
            ->keyBy('tool_id');

        return view('admin.tools.index', [
            'tools' => Tool::with('currentVersion')->orderBy('sort_order')->get()
                ->map(fn (Tool $tool) => [
                    'key' => $tool->key,
                    'title' => $tool->title,
                    'category' => $tool->category,
                    'status' => $tool->status,
                    'is_runnable' => $tool->isRunnable(),
                    'credit_cost' => $tool->currentVersion?->credit_cost,
                    'runs' => $tool->currentVersion?->toolRuns()->count() ?? 0,
                    'cost_usd' => round((float) ($costByTool[$tool->id]->cost ?? 0), 3),
                    'ai_calls' => (int) ($costByTool[$tool->id]->calls ?? 0),
                ])->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tools.form', [
            'tool' => null,
            'defaults' => $this->blankDefinition(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $definition = $this->validatedDefinition($request);

        if (Tool::where('key', $definition['key'])->exists()) {
            throw ValidationException::withMessages(['key' => 'مفتاح الأداة مستخدم بالفعل.']);
        }

        $tool = $this->builder->sync($definition);

        return redirect()->route('admin.tools.show', $tool->key)->with('status', 'أُنشئت الأداة.');
    }

    /**
     * بروفايلات المحاكاة — نفس سبعة scripts/audit-tool-adaptivity.php حرفيًا،
     * حتى يرى الآدمن ما يراه المدقق دون طرفية (بند ٢٨).
     */
    private const SIMULATION_PROFILES = [
        'فكرة (بلا نوع)' => ['project.stage' => 'idea', 'project.maturity' => 'early', 'project.has_website' => 'no', 'project.budget_band' => 'none', 'project.sector' => 'general'],
        'إطلاق خدمات' => ['project.stage' => 'launch', 'project.maturity' => 'early', 'project.business_model' => 'services', 'project.has_website' => 'no', 'project.budget_band' => 'small', 'project.sector' => 'services'],
        'متجر شغّال' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.business_model' => 'b2c', 'project.has_website' => 'yes', 'project.budget_band' => 'medium', 'project.sector' => 'ecommerce'],
        'خدمات B2B' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.business_model' => 'b2b', 'project.has_website' => 'yes', 'project.budget_band' => 'medium', 'project.sector' => 'services'],
        'اشتراك SaaS' => ['project.stage' => 'scale', 'project.maturity' => 'operating', 'project.business_model' => 'saas', 'project.has_website' => 'yes', 'project.budget_band' => 'large', 'project.sector' => 'saas'],
        'نشاط محلي' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.business_model' => 'b2c', 'project.has_website' => 'no', 'project.budget_band' => 'small', 'project.sector' => 'local'],
        'ضيف (محايد)' => ['project.stage' => 'growth', 'project.maturity' => 'operating', 'project.has_website' => 'no', 'project.budget_band' => 'unknown', 'project.sector' => 'general'],
    ];

    public function show(Tool $tool): View
    {
        $version = $tool->currentVersion()->with(['fields', 'prompts'])->first();
        $fields = $version?->fields ?? collect();
        $ruleFields = collect($version?->scoring_rules['rules'] ?? [])->pluck('field');

        // محاكي التكيف (بند ٢٨): كيف يرى كل نوع مشروع أسئلة هذه الأداة.
        $simulation = collect(self::SIMULATION_PROFILES)->map(function (array $context) use ($fields, $ruleFields): array {
            $visible = $fields->filter(fn ($field) => $field->isVisible($context));

            return [
                'questions' => $visible->count(),
                'required' => $visible->where('required', true)->count(),
                'scored' => $ruleFields->intersect($visible->pluck('key'))->count(),
                'keys' => $visible->pluck('key')->all(),
            ];
        });

        // ساحة المعاينة (بند ٢٩): الرسائل الفعلية التي تُرسل للنموذج في مرحلة
        // التركيب، بمدخل المثال الذهبي — بلا أي استدعاء ولا تكلفة.
        $example = \App\Services\Tools\GoldenExamples::catalog()[$tool->key] ?? null;
        $synthesisPrompt = ($version?->prompts ?? collect())->firstWhere('stage', 'synthesis');
        $preview = $synthesisPrompt === null ? null : implode("\n\n────────────────────\n\n", [
            "[system]\n".\App\Services\Tools\PipelineSchemas::systemPreamble($tool->key),
            "[برومبت الأداة — synthesis v".($version?->version ?? '?')."]\n".$synthesisPrompt->content,
            "[بيانات التشغيل — مدخل المثال الذهبي]\n".($example['input'] ?? 'لا مثال لهذه الأداة'),
        ]);

        return view('admin.tools.show', [
            'tool' => $tool,
            'version' => $version,
            'fields' => $fields,
            'prompts' => $version?->prompts ?? collect(),
            'simulation' => $simulation,
            'preview' => $preview,
            // تاريخ الإصدارات وزر السكّ (بند ١١).
            'versions' => $tool->versions()
                ->withCount(['prompts', 'prompts as locked_prompts_count' => fn ($query) => $query->whereNotNull('locked_at')])
                ->orderByDesc('version')->get(),
        ]);
    }

    /**
     * سكّ إصدار جديد من اللوحة (بند ١١) — نفس أمر tool:release حرفيًا.
     */
    public function release(Tool $tool): RedirectResponse
    {
        \Illuminate\Support\Facades\Artisan::call('tool:release', ['key' => $tool->key]);

        \App\Models\AuditLog::write('tool.release', $tool, ['output' => trim(\Illuminate\Support\Facades\Artisan::output())]);

        return back()->with('status', 'صدر إصدار جديد ببرومبتات غير مقفلة، وصار هو الفعّال.');
    }

    public function edit(Tool $tool): View
    {
        $version = $tool->currentVersion()->with('fields')->first();

        return view('admin.tools.form', [
            'tool' => $tool,
            'defaults' => [
                'key' => $tool->key,
                'name' => $tool->name,
                'title' => $tool->title,
                'description' => $tool->description,
                'pain' => $tool->pain,
                'promise' => $tool->promise,
                'audience' => $tool->audience,
                'duration_minutes' => $tool->duration_minutes,
                'category' => $tool->category,
                'sort_order' => $tool->sort_order,
                'status' => $tool->status,
                'credit_cost' => $version?->credit_cost ?? 5,
                'output_schema' => $this->pretty($version?->output_schema ?? PipelineSchemas::synthesis()),
                'scoring_rules' => $this->pretty($version?->scoring_rules ?? ['rules' => []]),
                'section_plan' => $this->pretty($version?->section_plan ?? []),
                'fields' => $this->pretty($this->fieldsToArray($version)),
            ],
        ]);
    }

    public function update(Request $request, Tool $tool): RedirectResponse
    {
        $definition = $this->validatedDefinition($request, $tool);
        $definition['key'] = $tool->key; // المفتاح لا يتغيّر بعد الإنشاء.

        // الحفاظ على البرومبتات الحالية (تُحرَّر من صفحة العرض).
        $definition['prompts'] = [];

        $this->builder->sync($definition);

        return redirect()->route('admin.tools.show', $tool->key)->with('status', 'حُدّثت الأداة.');
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        if ($tool->currentVersion?->toolRuns()->exists()) {
            return back()->withErrors(['tool' => 'لا يمكن حذف أداة استُخدمت. أخفِها بدل الحذف.']);
        }

        $tool->delete();

        return redirect()->route('admin.tools.index')->with('status', 'حُذفت الأداة.');
    }

    public function updateStatus(Request $request, Tool $tool): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.Tool::STATUS_PUBLISHED.','.Tool::STATUS_COMING_SOON,
        ]);

        if ($data['status'] === Tool::STATUS_PUBLISHED && $tool->current_version_id === null) {
            return back()->withErrors(['status' => 'لا يمكن نشر أداة بلا إصدار جاهز.']);
        }

        $tool->update(['status' => $data['status']]);

        return back()->with('status', 'حُدّثت حالة الأداة.');
    }

    public function updatePrompt(Request $request, Tool $tool, PromptVersion $prompt): RedirectResponse
    {
        abort_unless($prompt->tool_version_id === $tool->current_version_id, 404);

        if ($prompt->locked_at !== null) {
            return back()->withErrors(['prompt' => 'هذا البرومبت مقفل بعد الاستخدام (BR-012) ولا يُعدّل.']);
        }

        $data = $request->validate([
            'content' => 'required|string|min:10',
            'tier' => 'required|in:economy,standard,advanced',
        ]);

        $prompt->update($data);

        \App\Models\AuditLog::write('prompt.update', $prompt, ['tool' => $tool->key, 'stage' => $prompt->stage]);

        return back()->with('status', 'حُدّث البرومبت.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDefinition(Request $request, ?Tool $tool = null): array
    {
        $data = $request->validate([
            'key' => 'required|string|max:60|alpha_dash',
            'name' => 'required|string|max:120',
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:500',
            'pain' => 'nullable|string|max:300',
            'promise' => 'nullable|string|max:300',
            'audience' => 'nullable|string|max:300',
            'duration_minutes' => 'nullable|integer|min:1|max:120',
            'category' => 'required|string|max:60',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:published,coming_soon',
            'credit_cost' => 'required|integer|min:0|max:100',
            'output_schema' => 'required|string',
            'scoring_rules' => 'required|string',
            'section_plan' => 'required|string',
            'fields' => 'required|string',
        ]);

        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'title' => $data['title'],
            'description' => $data['description'],
            'pain' => $data['pain'] ?? null,
            'promise' => $data['promise'] ?? null,
            'audience' => $data['audience'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'category' => $data['category'],
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'],
            'version' => [
                'credit_cost' => $data['credit_cost'],
                'output_schema' => $this->decodeJson($data['output_schema'], 'output_schema'),
                'scoring_rules' => $this->decodeJson($data['scoring_rules'], 'scoring_rules'),
                'section_plan' => $this->decodeJson($data['section_plan'], 'section_plan'),
            ],
            'fields' => $this->decodeJson($data['fields'], 'fields'),
            'prompts' => $tool === null ? $this->starterPrompts($this->decodeJson($data['section_plan'], 'section_plan')) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw, string $field): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$field => 'صيغة JSON غير صحيحة في هذا الحقل.']);
        }

        return $decoded;
    }

    /**
     * برومبتات مبدئية لكل مرحلة عند إنشاء أداة جديدة، ليعدّلها الآدمن بعد ذلك.
     *
     * @param  array<int, array<string, mixed>>  $sectionPlan
     * @return array<string, string>
     */
    private function starterPrompts(array $sectionPlan): array
    {
        $prompts = [
            'gaps' => 'افحص البيانات واستخرج النواقص والتعارضات. أعد كائن JSON بالمفتاحين missing وconflicts فقط.',
            'consistency' => 'راجع الأقسام وابحث عن التناقض والتكرار والأرقام غير المسندة. أعد قائمة issues فقط.',
            'synthesis' => 'ركّب التقرير النهائي: نتائج وتوصيات وخطوة تالية. الدرجة محسوبة مسبقًا فلا تعد حسابها.',
        ];

        foreach ($sectionPlan as $section) {
            if (isset($section['key'])) {
                $prompts['section:'.$section['key']] = 'حلل هذا القسم واكتب headline ثم points. علّم الترجيحات بـ is_assumption = true.';
            }
        }

        return $prompts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fieldsToArray(?ToolVersion $version): array
    {
        if ($version === null) {
            return [];
        }

        return $version->fields->map(fn ($field) => array_filter([
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type,
            'step' => $field->step,
            'step_title' => $field->step_title,
            'required' => $field->required,
            'help' => $field->help,
            'why' => $field->why,
            'options' => $field->options,
            'validation' => $field->validation,
            'profile_key' => $field->profile_key,
            'visible_when' => $field->visible_when,
        ], fn ($v) => $v !== null))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function blankDefinition(): array
    {
        return [
            'key' => '', 'name' => '', 'title' => '', 'description' => '',
            'pain' => '', 'promise' => '', 'audience' => '', 'duration_minutes' => 10,
            'category' => '', 'sort_order' => 0, 'status' => 'coming_soon', 'credit_cost' => 5,
            'output_schema' => $this->pretty(PipelineSchemas::synthesis()),
            'scoring_rules' => $this->pretty(['rules' => [['field' => 'example', 'label' => 'مثال', 'type' => 'present', 'weight' => 10]]]),
            'section_plan' => $this->pretty([['key' => 'overview', 'title' => 'نظرة عامة', 'tier' => 'standard']]),
            'fields' => $this->pretty([['key' => 'example', 'label' => 'سؤال مثال', 'type' => 'textarea', 'step' => 1, 'step_title' => 'الخطوة الأولى', 'required' => true, 'why' => 'لماذا نسأل هذا']]),
        ];
    }

    private function pretty(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
