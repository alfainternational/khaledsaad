<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Services\Messaging\PersonaMessageProfileService;
use App\Services\Messaging\ProspectMessageService;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نظير العملاء المتوقعين في الواجهة البرمجية — نفس الخدمة ونفس السقف.
 */
class ProspectController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ProspectMessageService $messages,
        private readonly PersonaMessageProfileService $profiles,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json([
            'data' => [
                'channels' => MessageChannel::options(),
                'objectives' => MessageObjective::options(),
                'temperatures' => Prospect::TEMPERATURES,
                'batch_limit' => ProspectMessageService::BATCH_LIMIT,
                'prospects' => Prospect::where('project_id', $project->id)
                    ->where('status', '!=', Prospect::STATUS_ARCHIVED)
                    ->with(['messages' => fn ($query) => $query->latest('id')->limit(5)])
                    ->orderBy('name')->get()
                    ->map($this->payload(...))->all(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'organization' => 'nullable|string|max:160',
            'role' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:80',
            'interests' => 'nullable|array|max:8',
            'interests.*' => 'string|max:60',
            'notes' => 'nullable|string|max:2000',
            'temperature' => 'required|string|in:'.implode(',', array_keys(Prospect::TEMPERATURES)),
            'preferred_channel' => 'required|string|in:'.implode(',', array_keys(MessageChannel::options())),
            'persona_key' => 'nullable|string|max:64',
        ]);

        $panel = $project->personaPanel;
        $interests = $validated['interests'] ?? [];

        $prospect = Prospect::create([
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
            'persona_key' => filled($validated['persona_key'] ?? null)
                ? $validated['persona_key']
                : ($panel !== null
                    ? $this->profiles->bestMatch($panel, $validated['city'] ?? null, $interests)
                    : null),
            'status' => Prospect::STATUS_ACTIVE,
        ]);

        return response()->json(['data' => $this->payload($prospect->load('messages'))], 201);
    }

    public function generate(Request $request, Project $project): JsonResponse
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
            return response()->json(['message' => __('أضف عميلًا متوقعًا أولًا.')], 422);
        }

        $outcome = $this->messages->generate(
            $project,
            $prospects,
            MessageChannel::from($validated['channel']),
            MessageObjective::from($validated['objective']),
            $request->user(),
        );

        if ($outcome['messages'] === []) {
            return response()->json(['message' => __('تعذّر التوليد الآن. بياناتك لم تتأثر.')], 503);
        }

        return response()->json([
            'data' => array_map($this->messagePayload(...), $outcome['messages']),
            // ما لم يكتمل وما تجاوز السقف يُسمّيان صراحةً.
            'incomplete' => $outcome['failed'],
            'skipped' => $outcome['skipped'],
        ], 201);
    }

    public function markSent(Request $request, Project $project, ProspectMessage $message): JsonResponse
    {
        $this->authorizeProject($request, $project);

        if ($message->project_id !== $project->id) {
            return response()->json(['message' => __('غير موجود.')], 404);
        }

        $message->update(['status' => ProspectMessage::STATUS_SENT, 'sent_at' => now()]);

        return response()->json(['data' => $this->messagePayload($message->fresh())]);
    }

    public function update(Request $request, Project $project, Prospect $prospect): JsonResponse
    {
        $this->authorizeProject($request, $project);

        if ($prospect->project_id !== $project->id) {
            return response()->json(['message' => __('غير موجود.')], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:'.Prospect::STATUS_WON.','.Prospect::STATUS_ARCHIVED,
        ]);

        $prospect->update(['status' => $validated['status']]);

        return response()->json(['data' => $this->payload($prospect->fresh()->load('messages'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Prospect $prospect): array
    {
        return [
            'id' => $prospect->id,
            'name' => $prospect->name,
            'organization' => $prospect->organization,
            'role' => $prospect->role,
            'city' => $prospect->city,
            'interests' => $prospect->interests ?? [],
            'temperature' => $prospect->temperature,
            'temperature_label' => $prospect->temperatureLabel(),
            'preferred_channel' => $prospect->preferred_channel,
            'persona_key' => $prospect->persona_key,
            'status' => $prospect->status,
            'messages' => $prospect->messages->map($this->messagePayload(...))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(ProspectMessage $message): array
    {
        return [
            'id' => $message->id,
            'prospect_id' => $message->prospect_id,
            'channel' => $message->channel,
            'objective' => $message->objective,
            'content' => $message->content,
            'why' => $message->why,
            'origin' => $message->origin,
            'status' => $message->status,
            'status_label' => $message->statusLabel(),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
