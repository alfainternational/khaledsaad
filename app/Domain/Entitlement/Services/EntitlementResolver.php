<?php

namespace App\Domain\Entitlement\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Entitlement\Models\Entitlement;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class EntitlementResolver
{
    public function value(string $key, Workspace|Account|User|Plan|null $scope): mixed
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof Workspace) {
            $workspaceOverride = $this->findEntitlement('workspace', $scope->getKey(), $key);

            if ($workspaceOverride !== null) {
                return $workspaceOverride->decodedValue();
            }

            return $this->value($key, $scope->account);
        }

        if ($scope instanceof Account) {
            $subscription = $scope->subscription()->with('plan')->first();

            return $subscription?->plan
                ? $this->value($key, $subscription->plan)
                : null;
        }

        if ($scope instanceof Plan) {
            return $this->findEntitlement('plan', $scope->getKey(), $key)?->decodedValue();
        }

        return $this->findEntitlement('user', $scope->getKey(), $key)?->decodedValue();
    }

    public function boolean(string $key, Workspace|Account|User|Plan|null $scope, bool $default = false): bool
    {
        $value = $this->value($key, $scope);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allForPlan(Plan $plan): array
    {
        return Entitlement::query()
            ->where('scope_type', 'plan')
            ->where('scope_id', $plan->getKey())
            ->get()
            ->mapWithKeys(fn (Entitlement $entitlement): array => [
                $entitlement->key => $entitlement->decodedValue(),
            ])
            ->all();
    }

    private function findEntitlement(string $scopeType, int $scopeId, string $key): ?Entitlement
    {
        return Entitlement::query()
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->first();
    }
}
