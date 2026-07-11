<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\EnsureUserWorkspaceAccessAction;
use App\Application\Auth\RegisterUserAction;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterController
{
    /**
     * تسجيل مستخدم جديد وإصدار توكن Sanctum مباشرة (Bearer) للاستخدام في التطبيق.
     */
    public function store(
        RegisterRequest $request,
        RegisterUserAction $action,
        EnsureUserWorkspaceAccessAction $ensureAccess,
    ): JsonResponse {
        $user = $action->handle($request->validated());

        // تأكيد وجود مساحة عمل نشطة للمستخدم (نفس منطق الويب).
        $workspace = $ensureAccess->handle($user);

        $deviceName = (string) ($request->input('device_name') ?: 'mobile');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user))->resolve($request),
                'default_workspace_public_id' => $workspace?->public_id,
            ],
        ], 201);
    }
}
