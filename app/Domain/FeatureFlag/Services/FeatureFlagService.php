<?php

namespace App\Domain\FeatureFlag\Services;

use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Enums\FeatureFlagStatus;
use App\Models\User;
use App\Support\Contexts\AdminContext;
use App\Support\Contexts\WorkspaceContext;
use Carbon\CarbonInterface;

class FeatureFlagService
{
    public function isEnabled(string $key, AdminContext|WorkspaceContext|User|array|null $context = null): bool
    {
        $flag = FeatureFlag::query()
            ->with('audiences')
            ->where('key', $key)
            ->first();

        if (! $flag) {
            return false;
        }

        if ($flag->status === FeatureFlagStatus::Off) {
            return false;
        }

        if ($this->isExpired($flag->expires_at)) {
            return false;
        }

        [$userId, $workspaceId, $planId, $seed] = $this->extractContext($context);

        if (! $this->matchesAudience($flag, $userId, $workspaceId, $planId)) {
            return false;
        }

        if ($flag->rollout_percentage >= 100) {
            return true;
        }

        if ($seed === null) {
            return false;
        }

        $bucket = abs(crc32($seed)) % 100;

        return $bucket < $flag->rollout_percentage;
    }

    private function isExpired(?CarbonInterface $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * @return array{0:int|null,1:int|null,2:int|null,3:string|null}
     */
    private function extractContext(AdminContext|WorkspaceContext|User|array|null $context): array
    {
        if ($context instanceof AdminContext) {
            return [
                $context->user?->getKey(),
                null,
                null,
                $context->user?->public_id ?? $context->user?->email,
            ];
        }

        if ($context instanceof WorkspaceContext) {
            return [
                $context->user?->getKey(),
                $context->workspace?->getKey(),
                $context->plan?->getKey() ?? $context->workspace?->account?->subscription?->plan_id,
                $context->user?->public_id
                    ?? $context->workspace?->public_id
                    ?? $context->user?->email,
            ];
        }

        if ($context instanceof User) {
            return [$context->getKey(), null, null, $context->public_id ?? $context->email];
        }

        if (is_array($context)) {
            return [
                $context['user_id'] ?? null,
                $context['workspace_id'] ?? null,
                $context['plan_id'] ?? null,
                $context['seed'] ?? null,
            ];
        }

        return [null, null, null, null];
    }

    private function matchesAudience(FeatureFlag $flag, ?int $userId, ?int $workspaceId, ?int $planId): bool
    {
        if ($flag->audiences->isEmpty()) {
            return true;
        }

        foreach ($flag->audiences as $audience) {
            if ($audience->audience_type === 'user' && $userId !== null && $audience->audience_id === $userId) {
                return true;
            }

            if ($audience->audience_type === 'workspace' && $workspaceId !== null && $audience->audience_id === $workspaceId) {
                return true;
            }

            if ($audience->audience_type === 'plan' && $planId !== null && $audience->audience_id === $planId) {
                return true;
            }
        }

        return false;
    }
}
