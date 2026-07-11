<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\FinalizePayPalSubscriptionAction;
use App\Domain\AI\Services\AiCreditService;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Enums\PlanStatus;
use App\Http\Api\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CancelPayPalSubscriptionRequest;
use App\Http\Requests\Web\SubscribeBillingPlanRequest;
use App\Http\Resources\V1\PlanResource;
use App\Services\Billing\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * فوترة الموبايل — نفس حراسات الويب تماماً لكن بردود JSON،
 * والعودة من PayPal عبر جسر ويب عام يقفز إلى deep link التطبيق.
 */
class BillingController extends Controller
{
    /**
     * نظرة الفوترة: الباقات + الاشتراك الحالي + رصيد الذكاء + جاهزية PayPal.
     */
    public function show(
        Request $request,
        PayPalService $paypal,
        AiCreditService $credits,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('view', $workspace);

        $account = $workspace->account()->with('subscription.plan')->firstOrFail();
        $subscription = $account->subscription;

        $plans = Plan::query()
            ->where('status', PlanStatus::Active)
            ->orderBy('monthly_price')
            ->get();

        return response()->json([
            'data' => [
                'plans' => PlanResource::collection($plans)->resolve($request),
                'current_plan_code' => $subscription?->plan?->code,
                'subscription' => $subscription ? [
                    'status' => $subscription->status,
                    'billing_cycle' => $subscription->billing_cycle,
                    'current_period_end' => optional($subscription->current_period_end)->toIso8601String(),
                    'has_paypal' => filled($subscription->paypal_subscription_id),
                ] : null,
                'is_owner' => $request->user()->id === $account->owner_user_id,
                'ai_credits_balance' => $credits->balanceFor($account),
                'paypal_ready' => $paypal->isConfigured(),
            ],
        ]);
    }

    /**
     * بدء اشتراك PayPal — يعيد approval_url ليفتحه التطبيق في المتصفح.
     */
    public function subscribe(
        SubscribeBillingPlanRequest $request,
        PayPalService $paypal,
    ): JsonResponse {
        if (! $paypal->isConfigured()) {
            throw new ApiException('الدفع عبر PayPal غير مفعّل بعد.', 'PAYPAL_NOT_CONFIGURED', 503);
        }

        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $account = $workspace->account()->firstOrFail();

        if ($request->user()->id !== $account->owner_user_id) {
            throw ApiException::forbidden('إدارة الفوترة متاحة لمالك الحساب فقط.');
        }

        $plan = Plan::query()
            ->where('code', $request->validated('plan_code'))
            ->where('status', PlanStatus::Active)
            ->firstOrFail();

        if ($plan->code === 'free') {
            throw new ApiException('لا يمكن الاشتراك في الباقة المجانية عبر الدفع.', 'INVALID_PLAN', 422);
        }

        $free = Plan::query()->where('code', 'free')->firstOrFail();
        $subscription = $account->subscription ?? Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $free->id,
            'status' => 'active',
        ]);

        $billingCycle = $request->validated('billing_cycle');

        $monthlyId = filled($plan->paypal_plan_id_monthly)
            ? (string) $plan->paypal_plan_id_monthly
            : (string) config('services.paypal.plan_map.'.$plan->code.'.monthly', '');
        $annualId = filled($plan->paypal_plan_id_annual)
            ? (string) $plan->paypal_plan_id_annual
            : (string) config('services.paypal.plan_map.'.$plan->code.'.annual', '');

        try {
            $paypalPlanId = $paypal->resolveBillingPlanId($monthlyId, $annualId, $billingCycle);
        } catch (\InvalidArgumentException) {
            throw new ApiException(
                'لم تُربط هذه الباقة بمعرّف Billing Plan في PayPal بعد. تواصل مع الإدارة.',
                'PAYPAL_PLAN_UNMAPPED',
                503,
            );
        }

        if ($subscription->status === 'pending_payment' && $subscription->paypal_subscription_id) {
            throw new ApiException(
                'لديك عملية دفع قيد الانتظار. أكمل الموافقة في PayPal أو انتظر قليلاً.',
                'PAYMENT_PENDING',
                409,
            );
        }

        if ((int) $plan->id === (int) $subscription->plan_id
            && $subscription->status === 'active'
            && $subscription->checkout_plan_id === null) {
            throw new ApiException('أنت بالفعل على هذه الباقة.', 'ALREADY_ON_PLAN', 422);
        }

        $hasActivePaid = (int) $subscription->plan_id !== (int) $free->id
            && $subscription->status === 'active'
            && filled($subscription->paypal_subscription_id);

        if ($hasActivePaid) {
            throw new ApiException(
                'لديك اشتراك مدفوع نشط. ألغِ الاشتراك الحالي أولاً.',
                'ACTIVE_SUBSCRIPTION_EXISTS',
                409,
            );
        }

        try {
            $result = $paypal->createSubscription(
                $paypalPlanId,
                route('billing.mobile.return'),
                route('billing.mobile.cancelled'),
            );
        } catch (\Throwable) {
            throw new ApiException('تعذر بدء الدفع مع PayPal. حاول لاحقاً.', 'PAYPAL_ERROR', 503);
        }

        $subscription->forceFill([
            'paypal_subscription_id' => $result['subscription_id'],
            'checkout_plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'status' => 'pending_payment',
        ])->save();

        $approvalUrl = $result['approval_url'];
        if ($approvalUrl === null || $approvalUrl === '') {
            throw new ApiException('لم يُرجع PayPal رابط الموافقة.', 'PAYPAL_NO_APPROVAL_URL', 503);
        }

        return response()->json([
            'data' => [
                'approval_url' => $approvalUrl,
                'paypal_subscription_id' => $result['subscription_id'],
            ],
        ], 201);
    }

    /**
     * تأكيد العودة من PayPal (بعد الـ deep link) — تحقق وتفعيل.
     */
    public function callback(
        Request $request,
        PayPalService $paypal,
        FinalizePayPalSubscriptionAction $finalize,
    ): JsonResponse {
        $request->validate([
            'subscription_id' => ['required', 'string', 'max:100'],
        ]);

        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $account = $workspace->account()->firstOrFail();

        if ($request->user()->id !== $account->owner_user_id) {
            throw ApiException::forbidden('إدارة الفوترة متاحة لمالك الحساب فقط.');
        }

        $subscription = Subscription::query()
            ->where('account_id', $account->id)
            ->where('paypal_subscription_id', $request->input('subscription_id'))
            ->first();

        if ($subscription === null) {
            throw ApiException::notFound('لم نعثر على عملية الدفع المرتبطة بحسابك.');
        }

        try {
            $remote = $paypal->getSubscription((string) $request->input('subscription_id'));
        } catch (\Throwable) {
            throw new ApiException('تعذر التحقق من حالة الدفع في PayPal.', 'PAYPAL_ERROR', 503);
        }

        $remoteStatus = strtoupper((string) ($remote['status'] ?? ''));

        if (in_array($remoteStatus, ['ACTIVE', 'APPROVED'], true)) {
            $finalize->handle($subscription);

            return response()->json([
                'data' => [
                    'activated' => true,
                    'message' => 'تم تفعيل خطتك بنجاح.',
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'activated' => false,
                'paypal_status' => $remoteStatus,
                'message' => 'لم يكتمل الدفع بعد. حالة PayPal: '.$remoteStatus,
            ],
        ], 202);
    }

    /**
     * إلغاء اشتراك PayPal والعودة للمجانية.
     */
    public function cancel(
        CancelPayPalSubscriptionRequest $request,
        PayPalService $paypal,
        FinalizePayPalSubscriptionAction $finalize,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $account = $workspace->account()->firstOrFail();

        if ($request->user()->id !== $account->owner_user_id) {
            throw ApiException::forbidden('إدارة الفوترة متاحة لمالك الحساب فقط.');
        }

        $subscription = $account->subscription;
        if ($subscription === null || ! filled($subscription->paypal_subscription_id)) {
            throw ApiException::notFound('لا يوجد اشتراك PayPal نشط لإلغائه.');
        }

        try {
            $paypal->cancelSubscription(
                (string) $subscription->paypal_subscription_id,
                $request->validated('reason') ?? 'Cancelled by user',
            );
        } catch (\Throwable) {
            throw new ApiException('تعذر إلغاء الاشتراك في PayPal.', 'PAYPAL_ERROR', 503);
        }

        $finalize->downgradeToFree($subscription);

        return response()->json([
            'data' => [
                'cancelled' => true,
                'message' => 'تم إلغاء الاشتراك وإرجاع خطتك إلى المجانية.',
            ],
        ]);
    }
}
