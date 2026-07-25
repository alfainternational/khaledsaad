<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Feature;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Billing\FeatureKey;
use Illuminate\Http\JsonResponse;

class BillingCatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'features' => Feature::orderBy('group')->orderBy('sort_order')->get()
                ->map(fn (Feature $feature) => [
                    ...$feature->only([
                        'id', 'key', 'name', 'description', 'group', 'type', 'unit',
                        'enforcement', 'default_enabled', 'default_value', 'is_active', 'sort_order',
                    ]),
                    'is_wired' => in_array($feature->key, FeatureKey::all(), true),
                ])->all(),
            'plans' => Plan::with('planFeatures')->orderBy('sort_order')->get()
                ->map(fn (Plan $plan) => [
                    ...$plan->only([
                        'id', 'key', 'name', 'interval', 'price', 'monthly_credits',
                        'project_limit', 'features', 'is_public', 'sort_order',
                    ]),
                    'feature_items' => $plan->planFeatures->values()->all(),
                ])->all(),
            'packs' => CreditPack::orderBy('sort_order')->get()->map->only([
                'id', 'name', 'credits', 'price', 'currency', 'is_active', 'sort_order',
            ])->all(),
            'gateways' => PaymentGateway::orderBy('sort_order')->get()
                ->map(fn (PaymentGateway $gateway) => [
                    ...$gateway->only([
                        'id', 'provider', 'label', 'mode', 'is_active', 'currency',
                        'fx_rate', 'instructions', 'sort_order',
                    ]),
                    'configured' => $gateway->hasRequiredCredentials(),
                    'credential_fields' => collect(
                        PaymentGatewayManager::catalogue()[$gateway->provider]['fields'] ?? [],
                    )->map(fn (string $key) => [
                        'key' => $key,
                        'saved' => filled($gateway->credential($key)),
                    ])->values()->all(),
                ])->all(),
        ]]);
    }
}
