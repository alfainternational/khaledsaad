<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Workspace\CompleteWorkspaceOnboardingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CompleteOnboardingRequest;
use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use App\Support\Intelligence\SectorTemplateCatalog;
use App\Support\Workspaces\OnboardingState;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * حالة الـ onboarding + الملف الحالي + خيارات القوائم (للنموذج في التطبيق).
     */
    public function show(
        Request $request,
        OnboardingState $state,
        WorkspaceProfileStore $profileStore,
        SectorTemplateCatalog $sectorTemplateCatalog,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('view', $workspace);

        return response()->json([
            'data' => [
                'completed' => $state->isCompleted($workspace),
                'profile' => $profileStore->get($workspace),
                'workspace' => [
                    'name' => $workspace->name,
                    'type' => $workspace->type,
                ],
                'options' => [
                    'personas' => PersonaCatalog::options(),
                    'awareness_levels' => AwarenessCatalog::options(),
                    'goals' => GoalCatalog::options(),
                    'paths' => PathCatalog::options(),
                    'content_locales' => ContentLocaleCatalog::options(),
                    'sectors' => $sectorTemplateCatalog->options(),
                ],
            ],
        ]);
    }

    /**
     * إكمال الـ onboarding — نفس أكشن الويب تماماً.
     */
    public function store(
        CompleteOnboardingRequest $request,
        CompleteWorkspaceOnboardingAction $action,
        OnboardingState $state,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $account = $workspace->account()->firstOrFail();
        $action->handle($workspace, $account, $request->user(), $request->validated(), $state);

        return response()->json([
            'data' => [
                'completed' => true,
                'message' => 'اكتمل إعداد مساحة العمل.',
            ],
        ], 201);
    }
}
