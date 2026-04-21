<?php

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Support\Contexts\AdminContext;
use App\Support\Contexts\WorkspaceContext;

if (! function_exists('feature')) {
    function feature(string $key): bool
    {
        $user = auth()->user();
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        $context = $workspace
            ? new WorkspaceContext($workspace, $user, $workspace->account?->subscription?->plan)
            : new AdminContext($user);

        return app(FeatureFlagService::class)->isEnabled($key, $context);
    }
}

if (! function_exists('entitlement')) {
    function entitlement(string $key): mixed
    {
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
        $user = auth()->user();
        $scope = $workspace ?? $user;

        return app(EntitlementResolver::class)->value($key, $scope);
    }
}
