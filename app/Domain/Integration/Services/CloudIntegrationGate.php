<?php

namespace App\Domain\Integration\Services;

use App\Contracts\CloudClientContract;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Domain\Integration\Exceptions\CloudIntegrationException;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Contexts\WorkspaceContext;

/**
 * يتحقق من الإعداد، الـ feature flag، والـ entitlement قبل أي طلب صادر.
 */
final class CloudIntegrationGate
{
    public function __construct(
        private readonly CloudClientContract $client,
        private readonly FeatureFlagService $featureFlags,
        private readonly EntitlementResolver $entitlements,
    ) {}

    /**
     * @throws CloudIntegrationException
     */
    public function assertAllows(Workspace $workspace, ?User $user): void
    {
        if (! (bool) config('cloud.enabled', false)) {
            throw CloudIntegrationException::configurationMissing();
        }

        if (! $this->client->configured()) {
            throw CloudIntegrationException::configurationMissing();
        }

        $workspace->loadMissing('account.subscription.plan');

        if ((bool) config('cloud.policy.enforce_feature_flag', true)) {
            $flagKey = (string) config('cloud.feature_flag_key', 'integrations.cloud_http');
            $context = new WorkspaceContext(
                $workspace,
                $user,
                $workspace->account?->subscription?->plan
            );

            if (! $this->featureFlags->isEnabled($flagKey, $context)) {
                throw CloudIntegrationException::gateDenied('feature_flag');
            }
        }

        if ((bool) config('cloud.policy.enforce_entitlement', true)) {
            $entitlementKey = (string) config('cloud.entitlement_key', 'integrations.cloud_http');

            if (! $this->entitlements->boolean($entitlementKey, $workspace, false)) {
                throw CloudIntegrationException::gateDenied('entitlement');
            }
        }
    }
}
