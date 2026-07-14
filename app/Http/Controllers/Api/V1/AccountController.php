<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAccountApiRequest;
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
        UpdateAccountApiRequest $request,
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

        // دمج جزئي: نبدأ من الملف الحالي ونطبّق الحقول المُرسلة فقط، حتى لا يمسح
        // حفظٌ بسيط (كتغيير الاسم) بقيةَ الملف التسويقي لمستخدم لم يُكمله بعد.
        $profileKeys = [
            'persona',
            'awareness_level',
            'primary_goal',
            'recommended_path',
            'audience',
            'country',
            'content_locale',
            'current_challenge',
        ];

        $profile = $profileStore->get($workspace);
        foreach ($profileKeys as $key) {
            if ($request->has($key)) {
                $profile[$key] = $data[$key] ?? null;
            }
        }
        $profileStore->put($workspace, $profile);

        return response()->json([
            'data' => ['message' => 'تم تحديث إعدادات الحساب ومساحة العمل.'],
        ]);
    }
}
