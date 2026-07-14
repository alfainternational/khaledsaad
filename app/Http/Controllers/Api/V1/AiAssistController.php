<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Services\AiGatewayFactory;
use App\Domain\AI\Web\WebResearchService;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Api\AiChatController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * واجهة مساعد الذكاء للموبايل (v1/sanctum). تفوّض للمنطق الكامل في AiChatController
 * بعد ترجمة project_public_id إلى المعرّف الداخلي — لا تكرار منطق.
 */
class AiAssistController
{
    public function chat(Request $request): JsonResponse
    {
        $this->resolveProject($request);

        return app(AiChatController::class)->chat($request);
    }

    public function chatStream(Request $request): StreamedResponse
    {
        $this->resolveProject($request);

        return app(AiChatController::class)->chatStream($request, app(AiGatewayFactory::class));
    }

    public function analyze(Request $request): JsonResponse
    {
        $this->resolveProject($request);

        return app(AiChatController::class)->analyzeToolInputs($request);
    }

    public function suggest(Request $request): JsonResponse
    {
        $this->resolveProject($request);

        return app(AiChatController::class)->suggestFields($request);
    }

    public function research(Request $request): JsonResponse
    {
        return app(AiChatController::class)->research($request, app(WebResearchService::class));
    }

    /**
     * يترجم project_public_id (إن وُجد) إلى project_id داخلي ضمن مساحة العمل الحالية.
     */
    private function resolveProject(Request $request): void
    {
        $publicId = $request->input('project_public_id');
        if (! is_string($publicId) || $publicId === '') {
            return;
        }

        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $publicId)
            ->first();

        if ($project) {
            $request->merge(['project_id' => $project->id]);
        }
    }
}
