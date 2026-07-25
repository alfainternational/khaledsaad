<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|min:20|max:4096',
            'platform' => 'required|in:android,ios',
            'device_name' => 'nullable|string|max:120',
        ]);

        $hash = hash('sha256', $data['token']);
        $existing = DeviceToken::where('token_hash', $hash)->first();

        $device = DeviceToken::updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $request->user()->id,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => $device->only(['id', 'platform', 'device_name', 'last_seen_at']),
        ], $existing === null ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|min:20|max:4096',
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->delete();

        return response()->json(['data' => ['removed' => true]]);
    }
}
