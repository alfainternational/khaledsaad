<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\MessageTestBatch;
use App\Models\MessageTestResult;
use App\Models\MessageVariant;
use App\Models\PersonaPanel;
use App\Models\Project;
use App\Services\Messaging\MessageSuggestionService;
use App\Services\Messaging\MessageTestService;
use App\Services\Messaging\PersonaMessageProfileService;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use App\Support\Messaging\MessageStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * نظير الاستوديو في الواجهة البرمجية.
 *
 * التطبيق لا ينفّذ منطق اقتراح ولا تقييم محليًّا: نفس الخدمات ونفس
 * الحالات، فلا تنشأ نسخة ثانية من قواعد الرسائل داخل Flutter.
 */
class MessageStudioController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly PersonaMessageProfileService $profiles,
        private readonly MessageSuggestionService $suggestions,
        private readonly MessageTestService $tests,
    ) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        return response()->json([
            'data' => [
                'channels' => MessageChannel::options(),
                'objectives' => MessageObjective::options(),
                'statuses' => MessageStatus::all(),
                'limits' => array_reduce(
                    MessageChannel::cases(),
                    fn (array $carry, MessageChannel $case) => $carry + [$case->value => $case->maxLength()],
                    [],
                ),
                'panel' => $panel?->only(['id', 'personas', 'source', 'generated_at']),
                'personas' => $panel === null ? [] : $this->personas($panel),
                'batches' => $panel === null ? [] : MessageTestBatch::where('project_id', $project->id)
                    ->with('results')->latest('id')->limit(5)->get()
                    ->map(fn (MessageTestBatch $batch) => [
                        'id' => $batch->id,
                        'mode' => $batch->mode,
                        'status' => $batch->status,
                        'summary' => $batch->summary,
                        'created_at' => $batch->created_at?->toIso8601String(),
                        'results' => $batch->results->map(fn (MessageTestResult $result) => [
                            'id' => $result->id,
                            'persona_key' => $result->persona_key,
                            'message_variant_id' => $result->message_variant_id,
                            'score' => $result->score,
                            'reaction' => $result->reaction,
                            'strength' => $result->strength,
                            'objection' => $result->objection,
                            'revised_content' => $result->revised_content,
                        ])->all(),
                    ])->all(),
            ],
        ]);
    }

    public function suggest(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return response()->json(['message' => 'ابنِ لوحة الجمهور أولًا.'], 422);
        }

        $validated = $request->validate([
            'persona_key' => 'nullable|string|max:64',
            'channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'required|string|in:'.implode(',', array_keys(MessageObjective::options())),
        ]);

        $keys = filled($validated['persona_key'] ?? null)
            ? [$validated['persona_key']]
            : array_keys($this->profiles->profiles($panel));

        $outcome = $this->suggestions->suggest(
            $panel,
            $keys,
            MessageChannel::from($validated['channel']),
            MessageObjective::from($validated['objective']),
            $request->user(),
        );

        if ($outcome['variants'] === []) {
            return response()->json(['message' => 'تعذّر إنشاء الاقتراحات الآن.'], 503);
        }

        return response()->json([
            'data' => array_map($this->variantPayload(...), $outcome['variants']),
            // الشخصيات التي لم تكتمل تُسمّى للتطبيق كما تُسمّى في الويب.
            'incomplete' => $outcome['failed'],
        ], 201);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return response()->json(['message' => 'ابنِ لوحة الجمهور أولًا.'], 422);
        }

        $validated = $request->validate([
            'persona_key' => 'required|string|max:64',
            'channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'required|string|in:'.implode(',', array_keys(MessageObjective::options())),
            'content' => 'required|string|min:20',
            'parent_id' => 'nullable|integer',
        ]);

        if ($this->profiles->findPersona($panel, $validated['persona_key']) === null) {
            return response()->json(['message' => 'هذه الشخصية ليست في لوحة مشروعك.'], 422);
        }

        $channel = MessageChannel::from($validated['channel']);

        if (mb_strlen($validated['content']) > $channel->maxLength()) {
            return response()->json([
                'message' => "رسالة {$channel->label()} تتجاوز {$channel->maxLength()} محرفًا.",
            ], 422);
        }

        $parent = filled($validated['parent_id'] ?? null)
            ? MessageVariant::where('id', $validated['parent_id'])->where('persona_panel_id', $panel->id)->first()
            : null;

        $variant = MessageVariant::create([
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
        ]);

        return response()->json(['data' => $this->variantPayload($variant)], 201);
    }

    public function test(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return response()->json(['message' => 'ابنِ لوحة الجمهور أولًا.'], 422);
        }

        $validated = $request->validate([
            'variant_id' => 'nullable|integer',
            'channel' => 'nullable|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'objective' => 'nullable|string|in:'.implode(',', array_keys(MessageObjective::options())),
        ]);

        $variants = filled($validated['variant_id'] ?? null)
            ? MessageVariant::where('id', $validated['variant_id'])->where('persona_panel_id', $panel->id)->get()
            : MessageVariant::where('persona_panel_id', $panel->id)
                ->where('status', '!=', MessageVariant::STATUS_ARCHIVED)
                ->when(filled($validated['channel'] ?? null), fn ($q) => $q->where('channel', $validated['channel']))
                ->when(filled($validated['objective'] ?? null), fn ($q) => $q->where('objective', $validated['objective']))
                ->latest('id')->get()->unique('persona_key')->values();

        if ($variants->isEmpty()) {
            return response()->json(['message' => 'لا توجد رسالة صالحة للاختبار بعد.'], 422);
        }

        try {
            $batch = $this->tests->test(
                $panel,
                $variants,
                $request->user(),
                filled($validated['variant_id'] ?? null) ? MessageTestBatch::MODE_SINGLE : MessageTestBatch::MODE_BATCH,
            );
        } catch (Throwable) {
            return response()->json(['message' => 'تعذّر إجراء الاختبار الآن. رسائلك محفوظة.'], 503);
        }

        return response()->json([
            'data' => [
                'id' => $batch->id,
                'mode' => $batch->mode,
                'status' => $batch->status,
                'summary' => $batch->summary,
                'results' => $batch->results->map(fn (MessageTestResult $result) => [
                    'id' => $result->id,
                    'persona_key' => $result->persona_key,
                    'message_variant_id' => $result->message_variant_id,
                    'score' => $result->score,
                    'reaction' => $result->reaction,
                    'strength' => $result->strength,
                    'objection' => $result->objection,
                    'revised_content' => $result->revised_content,
                ])->all(),
            ],
        ], 201);
    }

    public function revise(Request $request, Project $project, MessageTestResult $result): JsonResponse
    {
        $this->authorizeProject($request, $project);

        if ($result->variant?->project_id !== $project->id) {
            return response()->json(['message' => 'غير موجود.'], 404);
        }

        if (blank($result->revised_content)) {
            return response()->json(['message' => 'لا يوجد تعديل مقترح لهذه النتيجة.'], 422);
        }

        return response()->json([
            'data' => $this->variantPayload($this->tests->reviseFrom($result, $request->user())),
        ], 201);
    }

    public function updateStatus(Request $request, Project $project, MessageVariant $variant): JsonResponse
    {
        $this->authorizeProject($request, $project);

        if ($variant->project_id !== $project->id) {
            return response()->json(['message' => 'غير موجود.'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:'.MessageVariant::STATUS_APPROVED.','.MessageVariant::STATUS_ARCHIVED,
        ]);

        $variant->update(['status' => $validated['status']]);

        return response()->json(['data' => $this->variantPayload($variant->fresh())]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function personas(PersonaPanel $panel): array
    {
        $variants = MessageVariant::where('persona_panel_id', $panel->id)
            ->latest('id')->get()->groupBy('persona_key');

        return array_map(function (array $persona) use ($panel, $variants): array {
            $key = $this->profiles->keyFor($persona);

            return [
                'persona_key' => $key,
                'profile' => $this->profiles->profile($key, $persona),
                'variants' => $variants->get($key, collect())
                    ->map($this->variantPayload(...))->values()->all(),
            ];
        }, $panel->personas ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function variantPayload(MessageVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'persona_key' => $variant->persona_key,
            'channel' => $variant->channel,
            'objective' => $variant->objective,
            'content' => $variant->content,
            'origin' => $variant->origin,
            'status' => $variant->status,
            'status_label' => MessageStatus::label($variant->status),
            'parent_id' => $variant->parent_id,
            'teaching_note' => $variant->teaching_note,
            'reusable_formula' => $variant->reusable_formula,
            'created_at' => $variant->created_at?->toIso8601String(),
        ];
    }
}
