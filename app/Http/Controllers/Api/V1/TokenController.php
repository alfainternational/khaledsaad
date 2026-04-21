<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Models\Workspace;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController
{
    /**
     * إصدار توكن Sanctum للاستخدام مع /api/v1/* (Bearer).
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'workspace_public_id' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => ['الحساب غير نشط.'],
            ]);
        }

        $abilities = [];
        if (! empty($validated['workspace_public_id'])) {
            $workspace = Workspace::query()->where('public_id', $validated['workspace_public_id'])->first();
            if (! $workspace) {
                throw ValidationException::withMessages([
                    'workspace_public_id' => ['مساحة العمل غير موجودة.'],
                ]);
            }
            $isMember = $workspace->members()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
            if (! $isMember) {
                throw ValidationException::withMessages([
                    'workspace_public_id' => ['ليس لديك عضوية في هذه مساحة العمل.'],
                ]);
            }
            $abilities = ['workspace:'.$workspace->public_id];
        }

        $token = $user->createToken($validated['device_name'], $abilities)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
            ],
        ]);
    }
}
