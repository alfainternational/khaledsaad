<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Controller;
use App\Models\User;
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
}
