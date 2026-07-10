<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateAccountSettingsRequest;
use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * نظرة الحساب الكاملة: الحساب + الباقة + المساحة + الملف + الصلاحيات + الخيارات.
     */
    public function show(
        Request $request,
        EntitlementResolver $resolver,
        WorkspaceProfileStore $profileStore,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('view', $workspace);

        $account = $workspace->account()->with('subscription.plan')->firstOrFail();
        $plan = $account->subscription?->plan;

        return response()->json([
            'data' => [
                'user' => [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'locale' => $request->user()->locale,
                ],
                'account' => [
                    'name' => $account->name,
                    'billing_email' => $account->billing_email,
                    'status' => $account->status,
                ],
                'plan' => $plan ? [
                    'code' => $plan->code,
                    'name' => $plan->name_ar ?? $plan->name,
                ] : null,
                'workspace' => [
                    'public_id' => $workspace->public_id,
                    'name' => $workspace->name,
                    'type' => $workspace->type,
                ],
                'profile' => $profileStore->get($workspace),
                'entitlements' => $plan ? $resolver->allForPlan($plan) : [],
                'options' => [
                    'personas' => PersonaCatalog::options(),
                    'awareness_levels' => AwarenessCatalog::options(),
                    'goals' => GoalCatalog::options(),
                    'paths' => PathCatalog::options(),
                    'content_locales' => ContentLocaleCatalog::options(),
                ],
            ],
        ]);
    }

    /**
     * تحديث إعدادات الحساب والمساحة والملف — نفس منطق الويب تماماً.
     */
    public function update(
        UpdateAccountSettingsRequest $request,
        WorkspaceProfileStore $profileStore,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $account = $workspace->account()->firstOrFail();
        $user = $request->user();
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'locale' => $data['locale'],
        ]);

        $account->update([
            'name' => $data['account_name'],
            'billing_email' => $data['billing_email'],
        ]);

        $workspace->update([
            'name' => $data['workspace_name'],
            'type' => $data['workspace_type'],
        ]);

        $profileStore->put($workspace, [
            'persona' => $data['persona'],
            'awareness_level' => $data['awareness_level'],
            'primary_goal' => $data['primary_goal'],
            'recommended_path' => $data['recommended_path'] ?? null,
            'audience' => $data['audience'],
            'country' => $data['country'],
            'content_locale' => $data['content_locale'],
            'current_challenge' => $data['current_challenge'] ?? null,
        ]);

        return response()->json([
            'data' => ['message' => 'تم تحديث إعدادات الحساب ومساحة العمل.'],
        ]);
    }
}
