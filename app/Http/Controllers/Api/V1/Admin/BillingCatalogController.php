<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminCreditPackController;
use App\Http\Controllers\Admin\AdminFeatureController;
use App\Http\Controllers\Admin\AdminGatewayController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Feature;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Billing\FeatureKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function storeFeature(Request $request, AdminFeatureController $features): JsonResponse
    {
        $features->store($request);

        return response()->json([
            'data' => Feature::where('key', $request->string('key'))->firstOrFail(),
        ], 201);
    }

    public function updateFeature(Request $request, Feature $feature, AdminFeatureController $features): JsonResponse
    {
        $features->update($request, $feature);

        return response()->json(['data' => $feature->fresh()]);
    }

    public function destroyFeature(Request $request, Feature $feature, AdminFeatureController $features): JsonResponse
    {
        $this->confirm($request);

        if (in_array($feature->key, FeatureKey::all(), true)) {
            return response()->json([
                'message' => __('هذا العنصر مربوط بالنظام. يمكنك تعطيله بدلاً من حذفه.'),
            ], 409);
        }

        $features->destroy($feature);

        return response()->json(['message' => __('حُذف عنصر الميزة.')]);
    }

    public function storePlan(Request $request, AdminPlanController $plans): JsonResponse
    {
        $plans->store($request);

        return response()->json([
            'data' => Plan::where('key', $request->string('key'))->firstOrFail()->load('planFeatures'),
        ], 201);
    }

    public function updatePlan(Request $request, Plan $plan, AdminPlanController $plans): JsonResponse
    {
        $plans->update($request, $plan);

        return response()->json(['data' => $plan->fresh()->load('planFeatures')]);
    }

    public function destroyPlan(Request $request, Plan $plan, AdminPlanController $plans): JsonResponse
    {
        $this->confirm($request);

        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => __('لا يمكن حذف خطة تضم مشتركين. يمكنك إخفاؤها بدلاً من حذفها.'),
            ], 409);
        }

        $plans->destroy($plan);

        return response()->json(['message' => __('حُذفت الخطة.')]);
    }

    public function storePack(Request $request, AdminCreditPackController $packs): JsonResponse
    {
        $packs->store($request);
        $pack = CreditPack::latest('id')->firstOrFail();

        return response()->json(['data' => $pack], 201);
    }

    public function updatePack(Request $request, CreditPack $pack, AdminCreditPackController $packs): JsonResponse
    {
        $packs->update($request, $pack);

        return response()->json(['data' => $pack->fresh()]);
    }

    public function destroyPack(Request $request, CreditPack $pack, AdminCreditPackController $packs): JsonResponse
    {
        $this->confirm($request);
        $packs->destroy($pack);

        return response()->json(['message' => __('حُذفت الحزمة.')]);
    }

    public function storeGateway(Request $request, AdminGatewayController $gateways): JsonResponse
    {
        $gateways->store($request);
        $gateway = PaymentGateway::where('provider', $request->string('provider'))->firstOrFail();

        return response()->json(['data' => $this->gateway($gateway)], 201);
    }

    public function updateGateway(
        Request $request,
        PaymentGateway $gateway,
        AdminGatewayController $gateways,
    ): JsonResponse {
        $gateways->update($request, $gateway);

        return response()->json(['data' => $this->gateway($gateway->fresh())]);
    }

    public function toggleGateway(
        Request $request,
        PaymentGateway $gateway,
        AdminGatewayController $gateways,
    ): JsonResponse {
        $this->confirm($request);

        if (! $gateway->is_active && ! $gateway->hasRequiredCredentials()) {
            return response()->json([
                'message' => __('أضف كل المفاتيح الإلزامية قبل تفعيل البوابة.'),
            ], 422);
        }
        if (! $gateway->is_active && $gateway->isLive() && ! $gateway->isHealthy()) {
            return response()->json(['message' => __('اختبر اتصال البوابة بنجاح قبل تفعيل الوضع المباشر.')], 422);
        }

        $gateways->toggle($gateway);

        return response()->json(['data' => $this->gateway($gateway->fresh())]);
    }

    public function testGateway(
        Request $request,
        PaymentGateway $gateway,
        AdminGatewayController $gateways,
    ): JsonResponse {
        $this->confirm($request);
        if (! $gateway->hasRequiredCredentials()) {
            return response()->json(['message' => __('أضف كل بيانات الربط الإلزامية أولًا.')], 422);
        }
        $health = app(PaymentGatewayManager::class)->provider($gateway)->healthCheck();
        $gateway->update([
            'health_status' => $health->healthy ? 'healthy' : 'unhealthy',
            'last_health_check_at' => now(),
            'last_health_message' => $health->message,
        ]);
        if (! $health->healthy) {
            return response()->json(['message' => $health->message, 'data' => $this->gateway($gateway->fresh())], 422);
        }

        return response()->json(['data' => $this->gateway($gateway->fresh())]);
    }

    public function defaultGateway(
        Request $request,
        PaymentGateway $gateway,
        AdminGatewayController $gateways,
    ): JsonResponse {
        $this->confirm($request);

        if (! $gateway->is_active || ! $gateway->hasRequiredCredentials()) {
            return response()->json(['message' => __('فعّل البوابة المهيأة أولًا.')], 422);
        }

        $gateways->setDefault($gateway);

        return response()->json(['data' => $this->gateway($gateway->fresh())]);
    }

    public function destroyGateway(
        Request $request,
        PaymentGateway $gateway,
        AdminGatewayController $gateways,
    ): JsonResponse {
        $this->confirm($request);
        if (Payment::where('payment_gateway_id', $gateway->id)->exists()) {
            return response()->json(['message' => __('لا يمكن حذف بوابة مرتبطة بمدفوعات سابقة.')], 409);
        }
        $gateways->destroy($gateway);

        return response()->json(['message' => __('حُذفت البوابة.')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function gateway(PaymentGateway $gateway): array
    {
        return [
            ...$gateway->only([
                'id', 'provider', 'label', 'mode', 'is_active', 'is_default', 'currency',
                'fx_rate', 'instructions', 'sort_order', 'health_status',
                'last_health_check_at', 'last_health_message',
            ]),
            'configured' => $gateway->hasRequiredCredentials(),
        ];
    }

    private function confirm(Request $request): void
    {
        $request->validate(['confirmation' => ['required', 'accepted']]);
    }
}
