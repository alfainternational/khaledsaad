<?php

namespace App\Http\Controllers\Web;

use App\Application\Billing\FinalizePayPalSubscriptionAction;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\CancelPayPalSubscriptionRequest;
use App\Http\Requests\Web\SubscribeBillingPlanRequest;
use App\Services\Billing\PayPalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function index(Request $request): View
    {
        $plans = Plan::query()
            ->where('status', PlanStatus::Active)
            ->orderBy('monthly_price')
            ->get();

        $paidPlans = $plans->filter(fn (Plan $p): bool => $p->code !== 'free')->values();

        $workspace = $this->currentWorkspace($request);
        $account = $workspace->account()->with(['subscription.plan'])->firstOrFail();
        $subscription = $account->subscription;
        $isOwner = $request->user()->id === $account->owner_user_id;

        $freePlan = Plan::query()->where('code', 'free')->first();

        return view('app.billing', [
            'plans' => $plans,
            'paidPlans' => $paidPlans,
            'currentPlanCode' => $subscription?->plan?->code,
            'workspace' => $workspace,
            'account' => $account,
            'subscription' => $subscription,
            'isOwner' => $isOwner,
            'freePlanId' => $freePlan?->id,
            'paypalReady' => app(PayPalService::class)->isConfigured(),
        ]);
    }

    public function subscribe(
        SubscribeBillingPlanRequest $request,
        PayPalService $paypal,
    ): RedirectResponse {
        if (! $paypal->isConfigured()) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'الدفع عبر PayPal غير مفعّل بعد. اضبط مفاتيح PayPal في ملف البيئة أو تواصل مع الإدارة.');
        }

        $workspace = $this->currentWorkspace($request);
        $account = $workspace->account()->firstOrFail();

        abort_unless($request->user()->id === $account->owner_user_id, 403);

        $plan = Plan::query()
            ->where('code', $request->validated('plan_code'))
            ->where('status', PlanStatus::Active)
            ->firstOrFail();

        abort_if($plan->code === 'free', 422);

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
            $planLabel = $plan->name_ar ?? $plan->code;
            $envHint = 'PAYPAL_PLAN_'.strtoupper($plan->code).'_MONTHLY / _ANNUAL';
            if (in_array($plan->code, ['starter', 'team'], true)) {
                $envHint .= ' (أو اضبط PAYPAL_PLAN_PRO_* فقط فيُستخدم كاحتياطي تلقائياً لهذه الباقة)';
            }
            if ($plan->code === 'agency') {
                $envHint .= ' أو PAYPAL_PLAN_ENT_*';
            }

            return redirect()
                ->route('billing.index')
                ->with('error', 'لم يتم ربط خطة «'.$planLabel.'» ('.$plan->code.') بمعرّف Billing Plan في PayPal (مثل P-xxxx). املأ الحقول في الإدارة → الخطط أو في .env ('.$envHint.'). لاستخراج المعرفات من حسابك شغّل: php artisan paypal:list-plans');
        }

        if ($subscription->status === 'pending_payment' && $subscription->paypal_subscription_id) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'لديك عملية دفع قيد الانتظار. أكمل الموافقة في PayPal أو انتظر قليلاً.');
        }

        if ((int) $plan->id === (int) $subscription->plan_id
            && $subscription->status === 'active'
            && $subscription->checkout_plan_id === null) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'أنت بالفعل على هذه الباقة. لاختيار باقة أخرى أو أعلى، غيّر الاختيار أعلاه أو اطلب من الإدارة إن لزم.');
        }

        $hasActivePaid = (int) $subscription->plan_id !== (int) $free->id
            && $subscription->status === 'active'
            && filled($subscription->paypal_subscription_id);

        if ($hasActivePaid) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'لديك اشتراك مدفوع نشط. لترقية الخطة أو تغييرها، ألغِ الاشتراك الحالي أولاً أو تواصل مع الدعم.');
        }

        try {
            $result = $paypal->createSubscription(
                $paypalPlanId,
                route('billing.paypal.return'),
                route('billing.index'),
            );
        } catch (\Throwable) {
            return redirect()
                ->route('billing.index')
                ->with('error', 'تعذر بدء الدفع مع PayPal. حاول لاحقاً أو تحقق من الإعدادات.');
        }

        $subscription->forceFill([
            'paypal_subscription_id' => $result['subscription_id'],
            'checkout_plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'status' => 'pending_payment',
        ])->save();

        $approvalUrl = $result['approval_url'];
        if ($approvalUrl === null || $approvalUrl === '') {
            return redirect()
                ->route('billing.index')
                ->with('error', 'لم يُرجع PayPal رابط الموافقة.');
        }

        return redirect()->away($approvalUrl);
    }

    public function paypalReturn(
        Request $request,
        PayPalService $paypal,
        FinalizePayPalSubscriptionAction $finalize,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $account = $workspace->account()->firstOrFail();

        abort_unless($request->user()->id === $account->owner_user_id, 403);

        $paypalSubId = (string) ($request->query('subscription_id') ?? $request->query('token') ?? '');
        if ($paypalSubId === '') {
            return redirect()->route('billing.index')->with('error', 'لم يصل معرف الاشتراك من PayPal.');
        }

        $subscription = Subscription::query()
            ->where('account_id', $account->id)
            ->where('paypal_subscription_id', $paypalSubId)
            ->first();

        if ($subscription === null) {
            return redirect()->route('billing.index')->with('error', 'لم نعثر على عملية الدفع المرتبطة بحسابك.');
        }

        try {
            $remote = $paypal->getSubscription($paypalSubId);
        } catch (\Throwable) {
            return redirect()->route('billing.index')->with('error', 'تعذر التحقق من حالة الدفع في PayPal.');
        }

        $remoteStatus = strtoupper((string) ($remote['status'] ?? ''));

        if (in_array($remoteStatus, ['ACTIVE', 'APPROVED'], true)) {
            $finalize->handle($subscription);

            return redirect()->route('billing.index')->with('status', 'تم تفعيل خطتك بنجاح.');
        }

        return redirect()->route('billing.index')->with('error', 'حالة الاشتراك في PayPal: '.$remoteStatus);
    }

    public function cancelPayPal(
        CancelPayPalSubscriptionRequest $request,
        PayPalService $paypal,
        FinalizePayPalSubscriptionAction $finalize,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $account = $workspace->account()->firstOrFail();

        abort_unless($request->user()->id === $account->owner_user_id, 403);

        $subscription = $account->subscription;
        if ($subscription === null || ! filled($subscription->paypal_subscription_id)) {
            return redirect()->route('billing.index')->with('error', 'لا يوجد اشتراك PayPal نشط لإلغائه.');
        }

        try {
            $paypal->cancelSubscription(
                (string) $subscription->paypal_subscription_id,
                $request->validated('reason') ?? 'Cancelled by user'
            );
        } catch (\Throwable) {
            return redirect()->route('billing.index')->with('error', 'تعذر إلغاء الاشتراك في PayPal.');
        }

        $finalize->downgradeToFree($subscription);

        return redirect()->route('billing.index')->with('status', 'تم إلغاء الاشتراك وإرجاع خطتك إلى المجانية.');
    }
}
