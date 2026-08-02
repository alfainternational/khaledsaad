<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\MessageTestBatch;
use App\Models\MessageTestResult;
use App\Models\MessageVariant;
use App\Models\PersonaPanel;
use App\Models\Project;
use App\Models\Report;
use App\Services\Growth\SyntheticAudience;
use App\Services\Messaging\MessageSuggestionService;
use App\Services\Messaging\MessageTestService;
use App\Services\Messaging\PersonaMessageProfileService;
use App\Services\Messaging\ToolMessageContextService;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * استوديو الرسائل: الشخصية وحدة العمل لا الرسالة.
 *
 * كل شخصية لها تبويبها ومسودتها وإصداراتها ونتائجها. لا تظهر في تبويب
 * شخصية نتيجة غيرها، ولا يُنتج الاستوديو في أي مسار نصًّا موحّدًا.
 */
class MessageStudioController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly SyntheticAudience $audience,
        private readonly PersonaMessageProfileService $profiles,
        private readonly MessageSuggestionService $suggestions,
        private readonly MessageTestService $tests,
        private readonly ToolMessageContextService $toolContext,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;
        $channel = $this->channel($request);
        $objective = $this->objective($request);
        $source = $this->source($project, $request->query('source'), $request->query('source_id'));

        return view('app.messages.studio', [
            'project' => $project,
            'panel' => $panel,
            'source' => $source,
            'personas' => $panel ? $this->personaTabs($panel, $channel, $objective) : [],
            'channel' => $channel,
            'objective' => $objective,
            'channels' => MessageChannel::options(),
            'objectives' => MessageObjective::options(),
            'batches' => $panel === null ? collect() : MessageTestBatch::where('project_id', $project->id)
                ->with('results.variant')->latest('id')->limit(5)->get(),
            // السجل القديم يُعرض ولا يُحوَّل: تحويله يخترع نية لم يحددها المستخدم.
            'legacyTests' => $panel?->tests()->latest('id')->limit(5)->get() ?? collect(),
        ]);
    }

    public function buildPanel(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $this->audience->buildPanel($project);

        return redirect()->route('app.messages.studio', $project)
            ->with('status', 'لوحة جمهورك جاهزة — لكل شخصية تبويبها الآن.');
    }

    /**
     * اقتراح مسودات: لشخصية واحدة أو للجميع، وكلٌّ نصُّها هي.
     */
    public function suggest(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return back()->withErrors(['studio' => 'ابنِ لوحة الجمهور أولًا.']);
        }

        $validated = $request->validate([
            'persona_key' => 'nullable|string|max:64',
            'channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'required|string|in:'.implode(',', array_keys(MessageObjective::options())),
            'source' => 'nullable|string|in:report',
            'source_id' => 'nullable|integer',
        ]);

        // filled لا !== null: المفتاح الغائب عن الطلب لا يظهر في نتيجة
        // validate أصلًا، فمقارنته بـ null ترمي «مفتاح غير معرّف».
        $keys = filled($validated['persona_key'] ?? null)
            ? [$validated['persona_key']]
            : array_keys($this->profiles->profiles($panel));

        // الحقائق تُستخرج على الخادم من معرّف التقرير، ولا تُقبل من النموذج
        // المرسَل: لو وثقنا بسياق يأتي مع الطلب لأمكن حقن «دليل» ملفَّق.
        $source = $this->source($project, $validated['source'] ?? null, $validated['source_id'] ?? null);

        $outcome = $this->suggestions->suggest(
            $panel,
            $keys,
            MessageChannel::from($validated['channel']),
            MessageObjective::from($validated['objective']),
            $request->user(),
            $source === null ? [] : [
                'type' => 'report',
                'id' => $source['report']->id,
                'context' => $source['context'],
            ],
        );

        $redirect = redirect()->route('app.messages.studio', [
            $project, 'channel' => $validated['channel'], 'objective' => $validated['objective'],
        ]);

        if ($outcome['variants'] === []) {
            return $redirect->withErrors(['studio' => 'تعذّر إنشاء الاقتراحات الآن. مسوداتك المحفوظة لم تتأثر.']);
        }

        // نجاح جزئي يُعلن: من لم تُكتب رسالته يُسمّى ولا يُترك فراغًا صامتًا.
        return $outcome['failed'] === []
            ? $redirect->with('status', 'كُتبت مسودة مستقلة لكل شخصية.')
            : $redirect->with('status', 'كُتبت '.count($outcome['variants']).' مسودة. '
                .count($outcome['failed']).' شخصية لم تكتمل — أعد المحاولة لها وحدها.');
    }

    /**
     * مسودة يدوية أو إصدار جديد من إصدار سابق.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return back()->withErrors(['studio' => 'ابنِ لوحة الجمهور أولًا.']);
        }

        $validated = $request->validate([
            'persona_key' => 'required|string|max:64',
            'channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'required|string|in:'.implode(',', array_keys(MessageObjective::options())),
            'content' => 'required|string|min:20',
            'parent_id' => 'nullable|integer',
        ]);

        if ($this->profiles->findPersona($panel, $validated['persona_key']) === null) {
            return back()->withErrors(['studio' => 'هذه الشخصية ليست في لوحة مشروعك.']);
        }

        $channel = MessageChannel::from($validated['channel']);

        if (mb_strlen($validated['content']) > $channel->maxLength()) {
            return back()->withErrors([
                'content' => "رسالة {$channel->label()} تتجاوز {$channel->maxLength()} محرفًا فتُقتطع عند النشر.",
            ])->withInput();
        }

        // الأب من مشروع آخر لا يُقبل مهما مُرّر معرّفه.
        $parent = filled($validated['parent_id'] ?? null)
            ? MessageVariant::where('id', $validated['parent_id'])
                ->where('persona_panel_id', $panel->id)->first()
            : null;

        MessageVariant::create([
            'project_id' => $project->id,
            'persona_panel_id' => $panel->id,
            'user_id' => $request->user()->id,
            'persona_key' => $validated['persona_key'],
            'channel' => $channel->value,
            'objective' => $validated['objective'],
            'content' => trim($validated['content']),
            'origin' => $parent !== null ? MessageVariant::ORIGIN_REVISED : MessageVariant::ORIGIN_MANUAL,
            'status' => MessageVariant::STATUS_DRAFT,
            'parent_id' => $parent?->id,
            'teaching_note' => $parent?->teaching_note,
            'reusable_formula' => $parent?->reusable_formula,
        ]);

        return redirect()->route('app.messages.studio', [
            $project, 'channel' => $channel->value, 'objective' => $validated['objective'],
        ])->with('status', 'حُفظت المسودة.');
    }

    public function test(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return back()->withErrors(['studio' => 'ابنِ لوحة الجمهور أولًا.']);
        }

        $validated = $request->validate([
            'variant_id' => 'nullable|integer',
            'channel' => 'nullable|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'nullable|string|in:'.implode(',', array_keys(MessageObjective::options())),
        ]);

        [$variants, $mode] = filled($validated['variant_id'] ?? null)
            ? [$this->singleVariant($panel, (int) $validated['variant_id']), MessageTestBatch::MODE_SINGLE]
            : [$this->latestPerPersona($panel, $validated['channel'] ?? null, $validated['objective'] ?? null),
                MessageTestBatch::MODE_BATCH];

        if ($variants->isEmpty()) {
            return back()->withErrors(['studio' => 'لا توجد رسالة صالحة للاختبار بعد.']);
        }

        try {
            $batch = $this->tests->test($panel, $variants, $request->user(), $mode);
        } catch (Throwable) {
            return back()->withErrors([
                'studio' => 'تعذّر إجراء الاختبار الآن. رسائلك محفوظة ولم تُفقد.',
            ]);
        }

        return redirect()->route('app.messages.studio', [
            $project, 'channel' => $validated['channel'] ?? null, 'objective' => $validated['objective'] ?? null,
        ])->with('status', $batch->status === MessageTestBatch::STATUS_PARTIAL
            ? 'اكتملت بعض النتائج فقط — الشخصيات الناقصة مذكورة في الخلاصة.'
            : 'جاهز — نتيجة كل شخصية على رسالتها هي.');
    }

    /**
     * تحويل تعديل مقترح إلى إصدار جديد دون الكتابة فوق المختبَر.
     */
    public function revise(Request $request, Project $project, MessageTestResult $result): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        if ($result->variant?->project_id !== $project->id) {
            abort(404);
        }

        if (blank($result->revised_content)) {
            return back()->withErrors(['studio' => 'لا يوجد تعديل مقترح لهذه النتيجة.']);
        }

        $this->tests->reviseFrom($result, $request->user());

        return back()->with('status', 'أُنشئ إصدار جديد من التعديل المقترح — الإصدار المختبَر باقٍ كما هو.');
    }

    public function updateStatus(Request $request, Project $project, MessageVariant $variant): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        if ($variant->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:'.MessageVariant::STATUS_APPROVED.','.MessageVariant::STATUS_ARCHIVED,
        ]);

        $variant->update(['status' => $validated['status']]);

        return back()->with('status', $validated['status'] === MessageVariant::STATUS_APPROVED
            ? 'اعتُمد هذا الإصدار.'
            : 'أُرشف هذا الإصدار.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function personaTabs(PersonaPanel $panel, MessageChannel $channel, MessageObjective $objective): array
    {
        $variants = MessageVariant::where('persona_panel_id', $panel->id)
            ->with(['results' => fn ($query) => $query->latest('id')])
            ->latest('id')->get()->groupBy('persona_key');

        $tabs = [];

        foreach ($panel->personas ?? [] as $persona) {
            $key = $this->profiles->keyFor($persona);
            $forPersona = $variants->get($key, collect());

            $tabs[] = [
                'key' => $key,
                'persona' => $persona,
                'profile' => $this->profiles->profile($key, $persona),
                // المسودة الحالية للقناة والهدف المختارين وحدهما.
                'current' => $forPersona->first(fn (MessageVariant $variant) => $variant->channel === $channel->value
                    && $variant->objective === $objective->value
                    && $variant->status !== MessageVariant::STATUS_ARCHIVED),
                'history' => $forPersona,
            ];
        }

        return $tabs;
    }

    /**
     * @return \Illuminate\Support\Collection<int, MessageVariant>
     */
    private function singleVariant(PersonaPanel $panel, int $variantId)
    {
        return MessageVariant::where('id', $variantId)
            ->where('persona_panel_id', $panel->id)
            ->get();
    }

    /**
     * أحدث إصدار غير مؤرشف لكل شخصية — رسالة واحدة لكل شخصية لا أكثر.
     *
     * @return \Illuminate\Support\Collection<int, MessageVariant>
     */
    private function latestPerPersona(PersonaPanel $panel, ?string $channel, ?string $objective)
    {
        return MessageVariant::where('persona_panel_id', $panel->id)
            ->where('status', '!=', MessageVariant::STATUS_ARCHIVED)
            ->when($channel !== null, fn ($query) => $query->where('channel', $channel))
            ->when($objective !== null, fn ($query) => $query->where('objective', $objective))
            ->latest('id')->get()
            ->unique('persona_key')->values();
    }

    /**
     * التقرير المصدر بعد التحقق من ملكيته وتأهيل أداته.
     *
     * تقرير من مشروع آخر يُهمَل بصمت لا يُرفع خطأً: الرابط قد يكون قديمًا،
     * وإسقاط السياق أهون من منع المستخدم من فتح استوديوه.
     *
     * @return array{report: Report, context: array<string, mixed>|null}|null
     */
    private function source(Project $project, mixed $type, mixed $id): ?array
    {
        if ($type !== 'report' || ! is_numeric($id)) {
            return null;
        }

        $report = Report::where('id', (int) $id)->where('project_id', $project->id)->first();

        if ($report === null || ! $this->toolContext->qualifies($report)) {
            return null;
        }

        return ['report' => $report, 'context' => $this->toolContext->contextFor($report)];
    }

    private function channel(Request $request): MessageChannel
    {
        return MessageChannel::tryFrom((string) $request->query('channel')) ?? MessageChannel::Ad;
    }

    private function objective(Request $request): MessageObjective
    {
        return MessageObjective::tryFrom((string) $request->query('objective')) ?? MessageObjective::Attention;
    }
}
