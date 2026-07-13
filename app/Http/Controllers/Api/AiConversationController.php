<?php

namespace App\Http\Controllers\Api;

use App\Domain\AI\Chat\AsyncChatService;
use App\Domain\AI\Chat\ChatMessageLifecycle;
use App\Domain\AI\Chat\Models\AiChatConversation;
use App\Domain\AI\Chat\Models\AiChatMessage;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiConversationController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function __construct(
        private readonly AsyncChatService $chat,
        private readonly ChatMessageLifecycle $messages,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->currentWorkspace($request);
        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        $paginator = AiChatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->with('project:id,public_id,name')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (AiChatConversation $conversation): array => $this->conversationData($conversation))->all(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_public_id' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'integer'],
            'tool_key' => ['nullable', 'string', 'max:100'],
        ]);
        $workspace = $this->currentWorkspace($request);
        $project = $this->project($workspace, $validated);
        $conversation = $this->chat->createConversation(
            $request->user(),
            $workspace,
            $project,
            (string) ($validated['tool_key'] ?? 'general'),
        );

        return response()->json(['data' => $this->conversationData($conversation)], 201);
    }

    public function show(Request $request, string $conversationPublicId): JsonResponse
    {
        $conversation = $this->ownedConversation($request, $conversationPublicId);
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $paginator = $conversation->messages()->latest('id')->paginate($perPage);

        return response()->json([
            'data' => $this->conversationData($conversation),
            'messages' => [
                'data' => $paginator->getCollection()
                    ->reverse()
                    ->map(fn (AiChatMessage $message): array => $this->messageData($this->reconcile($message)))
                    ->values()
                    ->all(),
                'meta' => $this->paginationMeta($paginator),
            ],
        ]);
    }

    public function storeMessage(Request $request, string $conversationPublicId): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'client_request_id' => ['required', 'string', 'max:100'],
        ]);
        $workspace = $this->currentWorkspace($request);
        $conversation = $this->ownedConversation($request, $conversationPublicId);
        $result = $this->chat->send(
            $request->user(),
            $workspace,
            $conversation,
            $validated['content'],
            $validated['client_request_id'],
        );

        return response()->json(['data' => [
            'conversation' => $this->conversationData($conversation->fresh()),
            'user_message' => $this->messageData($result['user_message']),
            'assistant_message' => $this->messageData($result['assistant_message']),
        ]], 202);
    }

    public function showMessage(Request $request, string $conversationPublicId, string $messagePublicId): JsonResponse
    {
        $conversation = $this->ownedConversation($request, $conversationPublicId);
        $message = $conversation->messages()->where('public_id', $messagePublicId)->firstOrFail();

        return response()->json(['data' => $this->messageData($this->reconcile($message))]);
    }

    private function ownedConversation(Request $request, string $publicId): AiChatConversation
    {
        $workspace = $this->currentWorkspace($request);

        return AiChatConversation::query()
            ->where('public_id', $publicId)
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->with('project:id,public_id,name')
            ->firstOrFail();
    }

    /** @param array<string, mixed> $input */
    private function project(Workspace $workspace, array $input): ?Project
    {
        $query = Project::query()->where('workspace_id', $workspace->id);
        if (! empty($input['project_public_id'])) {
            return $query->where('public_id', $input['project_public_id'])->firstOrFail();
        }
        if (! empty($input['project_id'])) {
            return $query->whereKey($input['project_id'])->firstOrFail();
        }

        return null;
    }

    private function reconcile(AiChatMessage $message): AiChatMessage
    {
        if (! in_array($message->status, ['queued', 'leased', 'processing'], true) || ! $message->intelligence_job_id) {
            return $message;
        }

        $job = $message->intelligenceJob()->first();
        if ($job?->status === 'completed' && is_array($job->result_json)) {
            DB::transaction(fn () => $this->messages->complete($job, $job->result_json));
        } elseif ($job && in_array($job->status, ['failed', 'cancelled'], true)) {
            DB::transaction(fn () => $this->messages->fail($job));
        }

        return $message->fresh();
    }

    /** @return array<string, mixed> */
    private function conversationData(AiChatConversation $conversation): array
    {
        return [
            'public_id' => $conversation->public_id,
            'title' => $conversation->title,
            'tool_key' => $conversation->tool_key,
            'project' => $conversation->project ? [
                'public_id' => $conversation->project->public_id,
                'name' => $conversation->project->name,
            ] : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function messageData(AiChatMessage $message): array
    {
        return [
            'public_id' => $message->public_id,
            'role' => $message->role,
            'content' => $message->content,
            'status' => $message->status,
            'error_code' => $message->error_code,
            'error_message' => $message->error_message,
            'created_at' => $message->created_at?->toIso8601String(),
            'completed_at' => $message->completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, int> */
    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
