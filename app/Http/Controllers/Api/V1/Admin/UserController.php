<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request, AdminUserController $users): JsonResponse
    {
        $payload = $users->payload($request);

        return response()->json([
            'data' => $payload['users']->values()->all(),
            'meta' => [
                'query' => $payload['search'],
                'limit' => 50,
            ],
        ]);
    }

    public function update(Request $request, User $user, AdminUserController $users): JsonResponse
    {
        $users->update($request, $user);

        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'is_admin'])]);
    }

    public function previewPlans(Request $request, SubscriptionAssignmentService $assignments): JsonResponse
    {
        $data = $this->planData($request, false);
        $plan = Plan::findOrFail($data['plan_id']);

        return response()->json(['data' => $assignments->preview(
            $data['workspace_ids'], $plan, $data['credit_policy'], $data['effective'],
        )]);
    }

    public function assignPlans(Request $request, SubscriptionAssignmentService $assignments): JsonResponse
    {
        $data = $this->planData($request, true);
        $plan = Plan::findOrFail($data['plan_id']);

        return response()->json(['data' => $assignments->assign(
            $data['workspace_ids'], $plan, $request->user(), $data['credit_policy'],
            $data['effective'], $data['credit_amount'] ?? null,
        )]);
    }

    public function grantCredits(Request $request, User $user, AdminUserController $users): JsonResponse
    {
        $this->confirm($request);
        $users->grantCredits($request, $user);

        return response()->json(['data' => [
            'user_id' => $user->id,
            'balance' => $user->primaryWorkspace()->wallet?->fresh()->balance ?? 0,
        ]]);
    }

    public function toggleAdmin(Request $request, User $user, AdminUserController $users): JsonResponse
    {
        $this->confirm($request);

        if ($user->is($request->user()) && $user->isAdmin()) {
            return response()->json(['message' => 'لا يمكنك نزع صلاحيتك عن نفسك.'], 409);
        }

        $users->toggleAdmin($request, $user);

        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'is_admin'])]);
    }

    private function confirm(Request $request): void
    {
        $request->validate(['confirmation' => ['required', 'accepted']]);
    }

    /** @return array<string, mixed> */
    private function planData(Request $request, bool $confirm): array
    {
        $rules = [
            'workspace_ids' => ['required', 'array', 'min:1', 'max:500'],
            'workspace_ids.*' => ['integer', 'distinct', 'exists:workspaces,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'credit_policy' => ['required', 'in:keep,plan_grant,add'],
            'credit_amount' => ['nullable', 'required_if:credit_policy,add', 'integer', 'min:1', 'max:1000000'],
            'effective' => ['required', 'in:now,period_end'],
        ];
        if ($confirm) {
            $rules['confirmation'] = ['required', 'accepted'];
        }

        return $request->validate($rules);
    }
}
