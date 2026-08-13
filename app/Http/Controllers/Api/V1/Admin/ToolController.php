<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminToolController;
use App\Http\Controllers\Controller;
use App\Models\PromptVersion;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    public function index(): JsonResponse
    {
        $tools = Tool::with(['currentVersion.fields', 'currentVersion.prompts'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Tool $tool) => $this->present($tool))
            ->all();

        return response()->json(['data' => $tools]);
    }

    public function show(Tool $tool): JsonResponse
    {
        $tool->load(['currentVersion.fields', 'currentVersion.prompts']);

        return response()->json(['data' => $this->present($tool)]);
    }

    public function store(Request $request, AdminToolController $tools): JsonResponse
    {
        $tools->store($request);
        $tool = Tool::where('key', $request->string('key'))->firstOrFail();

        return $this->show($tool)->setStatusCode(201);
    }

    public function update(Request $request, Tool $tool, AdminToolController $tools): JsonResponse
    {
        $tools->update($request, $tool);

        return $this->show($tool->fresh());
    }

    public function destroy(Request $request, Tool $tool, AdminToolController $tools): JsonResponse
    {
        $this->confirm($request);

        if ($tool->currentVersion?->toolRuns()->exists()) {
            return response()->json([
                'message' => __('لا يمكن حذف أداة استُخدمت. يمكنك إخفاؤها بدلاً من حذفها.'),
            ], 409);
        }

        $tools->destroy($tool);

        return response()->json(['message' => __('حُذفت الأداة.')]);
    }

    public function updateStatus(Request $request, Tool $tool, AdminToolController $tools): JsonResponse
    {
        $this->confirm($request);

        if ($request->string('status')->toString() === Tool::STATUS_PUBLISHED && $tool->current_version_id === null) {
            return response()->json(['message' => __('لا يمكن نشر أداة بلا إصدار جاهز.')], 422);
        }

        $tools->updateStatus($request, $tool);

        return $this->show($tool->fresh());
    }

    public function updatePrompt(
        Request $request,
        Tool $tool,
        PromptVersion $prompt,
        AdminToolController $tools,
    ): JsonResponse {
        abort_unless($prompt->tool_version_id === $tool->current_version_id, 404);

        if ($prompt->locked_at !== null) {
            return response()->json([
                'message' => __('هذا البرومبت مقفل بعد الاستخدام. أنشئ إصدار أداة جديداً.'),
            ], 409);
        }

        $tools->updatePrompt($request, $tool, $prompt);

        return response()->json(['data' => [
            'id' => $prompt->id,
            'stage' => $prompt->stage,
            'tier' => $prompt->fresh()->tier,
            'content' => $prompt->fresh()->content,
            'locked' => false,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Tool $tool): array
    {
        $version = $tool->currentVersion;

        return [
            'key' => $tool->key,
            'name' => $tool->name,
            'title' => $tool->title,
            'description' => $tool->description,
            'pain' => $tool->pain,
            'promise' => $tool->promise,
            'audience' => $tool->audience,
            'duration_minutes' => $tool->duration_minutes,
            'category' => $tool->category,
            'status' => $tool->status,
            'sort_order' => $tool->sort_order,
            'is_runnable' => $tool->isRunnable(),
            'current_version' => $version === null ? null : [
                'id' => $version->id,
                'version' => $version->version,
                'credit_cost' => $version->credit_cost,
                'status' => $version->status,
                'output_schema' => $version->output_schema,
                'scoring_rules' => $version->scoring_rules,
                'section_plan' => $version->section_plan,
                'fields' => $version->fields->values()->all(),
                'prompts' => $version->prompts->map(fn ($prompt) => [
                    'id' => $prompt->id,
                    'stage' => $prompt->stage,
                    'tier' => $prompt->tier,
                    'content' => $prompt->content,
                    'status' => $prompt->status,
                    'locked' => $prompt->locked_at !== null,
                ])->values()->all(),
            ],
        ];
    }

    private function confirm(Request $request): void
    {
        $request->validate(['confirmation' => ['required', 'accepted']]);
    }
}
