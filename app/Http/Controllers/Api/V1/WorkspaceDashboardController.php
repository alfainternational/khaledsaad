<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Dashboard\DashboardResolver;
use App\Support\Workspaces\OnboardingState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceDashboardController extends Controller
{
    /**
     * لقطة الداشبورد الكاملة لمساحة العمل (نفس مصدر بيانات الويب).
     */
    public function show(
        Request $request,
        DashboardResolver $dashboardResolver,
        OnboardingState $state,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('view', $workspace);

        $workspace->loadMissing('account.subscription.plan');

        return response()->json([
            'data' => [
                'onboarding_completed' => $state->isCompleted($workspace),
                'dashboard' => $dashboardResolver->resolve($workspace, $request->user()),
            ],
        ]);
    }
}
