<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notification\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceTokenController
{
    /**
     * تسجيل (أو تحديث) توكن جهاز للإشعارات.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        DeviceToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
            ],
            [
                'platform' => $validated['platform'] ?? 'android',
                'device_name' => $validated['device_name'] ?? null,
            ],
        );

        return response()->json([
            'data' => ['registered' => true],
        ], 201);
    }

    /**
     * إلغاء تسجيل توكن (عند الخروج).
     */
    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->noContent();
    }
}
