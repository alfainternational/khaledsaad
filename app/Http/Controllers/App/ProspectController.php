<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Services\Messaging\PersonaMessageProfileService;
use App\Services\Messaging\ProspectMessageService;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * العملاء المتوقعون: رسالة باسم كل واحد منهم.
 *
 * لا إرسال من المنصة ولا حفظ لهاتف أو بريد: الرسالة تُنسخ ويرسلها صاحب
 * المشروع من أداته. المنصة تكتب، ولا تنوب عن أحد في مخاطبة أحد.
 */
class ProspectController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ProspectMessageService $messages,
        private readonly PersonaMessageProfileService $profiles,
    ) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        return view('app.prospects.index', [
            'project' => $project,
            'panel' => $panel,
            'personas' => $panel === null ? [] : collect($panel->personas ?? [])
                ->mapWithKeys(fn (array $persona) => [
                    $this->profiles->keyFor($persona) => $persona['name'] ?? 'شخصية',
                ])->all(),
            'prospects' => Prospect::where('project_id', $project->id)
                ->where('status', '!=', Prospect::STATUS_ARCHIVED)
                ->with(['messages' => fn ($query) => $query->latest('id')])
                ->orderBy('name')->get(),
            'channels' => MessageChannel::options(),
            'objectives' => MessageObjective::options(),
            'channel' => MessageChannel::tryFrom((string) $request->query('channel')) ?? MessageChannel::Whatsapp,
            'objective' => MessageObjective::tryFrom((string) $request->query('objective')) ?? MessageObjective::Trust,
            'temperatures' => Prospect::TEMPERATURES,
            'batchLimit' => ProspectMessageService::BATCH_LIMIT,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'organization' => 'nullable|string|max:160',
            'role' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:80',
            'interests' => 'nullable|string|max:400',
            'notes' => 'nullable|string|max:2000',
            'temperature' => 'required|string|in:'.implode(',', array_keys(Prospect::TEMPERATURES)),
            'preferred_channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'persona_key' => 'nullable|string|max:64',
        ], [
            'name.required' => 'الاسم إلزامي — الرسالة تُخاطب شخصًا لا سجلًّا.',
        ]);

        $interests = $this->splitList($validated['interests'] ?? '');
        $panel = $project->personaPanel;

        // مطابقة مقترحة حين لا يختار المستخدم، وnull حين لا يتطابق شيء.
        $personaKey = filled($validated['persona_key'] ?? null)
            ? $validated['persona_key']
            : ($panel !== null ? $this->profiles->bestMatch($panel, $validated['city'] ?? null, $interests) : null);

        Prospect::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'organization' => $validated['organization'] ?? null,
            'role' => $validated['role'] ?? null,
            'city' => $validated['city'] ?? null,
            'interests' => $interests ?: null,
            'notes' => $validated['notes'] ?? null,
            'temperature' => $validated['temperature'],
            'preferred_channel' => $validated['preferred_channel'],
            'persona_key' => $personaKey,
            'status' => Prospect::STATUS_ACTIVE,
        ]);

        return back()->with('status', 'أُضيف '.$validated['name'].' — ولّد رسالته حين تشاء.');
    }

    /**
     * توليد رسالة لعميل واحد أو للقائمة كلها.
     */
    public function generate(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'prospect_id' => 'nullable|integer',
            'channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'required|string|in:'.implode(',', array_keys(MessageObjective::options())),
        ]);

        $prospects = Prospect::where('project_id', $project->id)
            ->where('status', Prospect::STATUS_ACTIVE)
            ->when(filled($validated['prospect_id'] ?? null),
                fn ($query) => $query->where('id', $validated['prospect_id']))
            ->orderBy('id')->get();

        if ($prospects->isEmpty()) {
            return back()->withErrors(['prospects' => 'أضف عميلًا متوقعًا أولًا.']);
        }

        $outcome = $this->messages->generate(
            $project,
            $prospects,
            MessageChannel::from($validated['channel']),
            MessageObjective::from($validated['objective']),
            $request->user(),
        );

        $redirect = back();

        if ($outcome['messages'] === []) {
            return $redirect->withErrors([
                'prospects' => 'تعذّر التوليد الآن. بيانات عملائك ورسائلهم السابقة لم تتأثر.',
            ]);
        }

        $notes = [count($outcome['messages']).' رسالة جاهزة.'];

        if ($outcome['failed'] !== []) {
            $notes[] = 'لم تكتمل: '.implode('، ', $outcome['failed']).'.';
        }

        if ($outcome['skipped'] > 0) {
            // السقف يُعلَن ولا يُقتطع بصمت.
            $notes[] = 'تُركت '.$outcome['skipped'].' خارج هذه الدفعة (الحد '
                .ProspectMessageService::BATCH_LIMIT.' في المرة) — أعد التوليد لبقيتهم.';
        }

        return $redirect->with('status', implode(' ', $notes));
    }

    /**
     * مسودة يدوية: من يعرف عميله أكثر يكتب بنفسه.
     */
    public function storeMessage(Request $request, Project $project, Prospect $prospect): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->assertOwns($project, $prospect);

        $validated = $request->validate([
            'content' => 'required|string|min:20',
            'channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'required|string|in:'.implode(',', array_keys(MessageObjective::options())),
        ]);

        $channel = MessageChannel::from($validated['channel']);

        if (mb_strlen($validated['content']) > $channel->maxLength()) {
            return back()->withErrors([
                'prospects' => "رسالة {$channel->label()} تتجاوز {$channel->maxLength()} محرفًا فتُقتطع عند الإرسال.",
            ])->withInput();
        }

        ProspectMessage::create([
            'prospect_id' => $prospect->id,
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'channel' => $channel->value,
            'objective' => $validated['objective'],
            'content' => trim($validated['content']),
            'origin' => ProspectMessage::ORIGIN_MANUAL,
            'status' => ProspectMessage::STATUS_DRAFT,
            'parent_id' => $prospect->latestMessage()?->id,
        ]);

        return back()->with('status', 'حُفظت رسالتك لـ'.$prospect->name.'.');
    }

    /**
     * «أُرسلت» يسجّلها صاحب المشروع بنفسه — المنصة لا ترسل ولا تدّعي.
     */
    public function markSent(Request $request, Project $project, ProspectMessage $message): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        if ($message->project_id !== $project->id) {
            abort(404);
        }

        $message->update([
            'status' => ProspectMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        return back()->with('status', 'سُجّلت كمُرسَلة.');
    }

    public function updateProspect(Request $request, Project $project, Prospect $prospect): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->assertOwns($project, $prospect);

        $validated = $request->validate([
            'status' => 'required|string|in:'.Prospect::STATUS_WON.','.Prospect::STATUS_ARCHIVED,
        ]);

        $prospect->update(['status' => $validated['status']]);

        return back()->with('status', $validated['status'] === Prospect::STATUS_WON
            ? 'مبروك — نُقل إلى العملاء الفائزين.'
            : 'أُرشف العميل.');
    }

    private function assertOwns(Project $project, Prospect $prospect): void
    {
        if ($prospect->project_id !== $project->id) {
            abort(404);
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitList(string $value): array
    {
        // المعدّل u إلزامي: الفاصلة العربية متعددة البايتات.
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[،,\n]+/u', $value) ?: [],
        )));
    }
}
