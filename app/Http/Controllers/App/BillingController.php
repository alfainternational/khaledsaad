<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Billing\CreditManager;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionManager;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly CreditManager $credits,
        private readonly PaymentGatewayManager $gateways,
        private readonly Entitlements $entitlements,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $request->user()->primaryWorkspace();
        $subscription = $this->subscriptions->ensure($workspace);
        $wallet = $this->credits->walletFor($workspace);
        $activeGateways = $this->gateways->activeGateways();

        return view('app.billing.index', [
            'current_plan' => $subscription->plan->key,
            'balance' => $wallet->balance,
            'project_count' => $workspace->projects()->count(),
            'project_limit' => $subscription->plan->project_limit,
            'payments_enabled' => $this->gateways->hasActiveGateway(),
            'payments' => Payment::where('workspace_id', $workspace->id)->latest('id')->limit(20)->get()
                ->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'purpose' => $payment->purpose === 'plan' ? 'اشتراك' : 'حزمة رصيد',
                    'provider' => $payment->gateway?->label ?? $payment->provider,
                    'amount' => $payment->amount.' '.$payment->currency,
                    'status' => $payment->statusLabel(),
                    'refunded' => (float) $payment->refunded_amount,
                    'at' => $payment->created_at->translatedFormat('j F Y'),
                ])->all(),
            'subscription_period_end' => $subscription->current_period_ends_at?->translatedFormat('j F Y'),
            'scheduled_plan' => $subscription->scheduledPlan?->name,
            'gateways' => $activeGateways->map(fn ($gateway) => [
                'id' => $gateway->id,
                'label' => $gateway->label,
                'provider' => $gateway->provider,
                'instructions' => $gateway->instructions,
                'is_default' => $gateway->is_default || $gateway->id === $activeGateways->first()?->id,
            ])->all(),
            'packs' => CreditPack::active()->orderBy('sort_order')->get()
                ->map(fn (CreditPack $pack) => [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'credits' => $pack->credits,
                    'price' => $pack->price,
                    'currency' => $pack->currency,
                ])->all(),
            'plans' => Plan::where('is_public', true)->with('planFeatures')->orderBy('sort_order')->get()
                ->map(fn (Plan $plan) => [
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'monthly_credits' => $plan->monthly_credits,
                    'project_limit' => $plan->project_limit,
                    // الميزات من عناصرها لا من نصوص: ما يُعرض هو ما يُطبَّق.
                    'features' => $this->entitlements->displayFeatures($plan),
                    'is_current' => $plan->id === $subscription->plan_id,
                ])->all(),
            'transactions' => $wallet->transactions()->limit(15)->get()
                ->map(fn ($transaction) => [
                    'type_label' => $transaction->typeLabel(),
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                    'reason' => $transaction->reason,
                    'at' => $transaction->created_at->translatedFormat('j F Y'),
                ])->all(),
        ]);
    }

    /**
     * الخطة المجانية تُفعَّل مباشرة. المدفوعة تمر بالبوابة إجباريًا —
     * كان هذا المسار يمنح الخطط المدفوعة مجانًا، وهو ثقب فوترة لا ميزة.
     */
    public function subscribe(Request $request, Plan $plan): RedirectResponse
    {
        if ($plan->price > 0) {
            return redirect()->route('app.billing')
                ->withErrors(['plan' => 'هذه خطة مدفوعة: أتمم الدفع من زر الاشتراك.']);
        }

        $this->subscriptions->subscribe($request->user()->primaryWorkspace(), $plan);

        return redirect()->route('app.billing')
            ->with('status', "فُعّلت خطة «{$plan->name}» وأُضيف رصيدها إلى محفظتك.");
    }
}
