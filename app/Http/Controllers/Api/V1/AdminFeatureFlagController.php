<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PatchFeatureFlagRequest;
use Illuminate\Http\JsonResponse;

class AdminFeatureFlagController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = FeatureFlag::query()
            ->orderBy('key')
            ->get(['id', 'public_id', 'key', 'name', 'module', 'status', 'rollout_percentage', 'expires_at']);

        return response()->json([
            'data' => $rows->map(fn (FeatureFlag $f): array => [
                'public_id' => $f->public_id,
                'key' => $f->key,
                'name' => $f->name,
                'module' => $f->module,
                'status' => $f->status->value,
                'rollout_percentage' => $f->rollout_percentage,
                'expires_at' => $f->expires_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function update(PatchFeatureFlagRequest $request, string $key, AuditLogger $auditLogger): JsonResponse
    {
        $flag = FeatureFlag::query()->where('key', $key)->firstOrFail();

        $flag->fill($request->validated());
        $flag->save();

        $auditLogger->record(
            action: 'admin.feature-flag.updated',
            targetType: 'feature_flag',
            targetId: $flag->getKey(),
            actor: $request->user(),
            meta: ['key' => $flag->key, 'via' => 'api.v1']
        );

        return response()->json([
            'data' => [
                'key' => $flag->key,
                'status' => $flag->status->value,
                'rollout_percentage' => $flag->rollout_percentage,
                'expires_at' => $flag->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
