<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;

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
}
