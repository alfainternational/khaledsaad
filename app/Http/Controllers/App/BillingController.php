<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Plan;
use App\Services\Billing\CreditManager;
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
    ) {}

    public function index(Request $request): View
    {
        $workspace = $request->user()->primaryWorkspace();
        $subscription = $this->subscriptions->ensure($workspace);
        $wallet = $this->credits->walletFor($workspace);

        return view('app.billing.index', [
            'current_plan' => $subscription->plan->key,
            'balance' => $wallet->balance,
            'project_count' => $workspace->projects()->count(),
            'project_limit' => $subscription->plan->project_limit,
            'payments_enabled' => $this->gateways->hasActiveGateway(),
            'packs' => CreditPack::active()->orderBy('sort_order')->get()
                ->map(fn (CreditPack $pack) => [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'credits' => $pack->credits,
                    'price' => $pack->price,
                    'currency' => $pack->currency,
                ])->all(),
            'plans' => Plan::where('is_public', true)->orderBy('sort_order')->get()
                ->map(fn (Plan $plan) => [
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'monthly_credits' => $plan->monthly_credits,
                    'project_limit' => $plan->project_limit,
                    'features' => $plan->features ?? [],
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

    public function subscribe(Request $request, Plan $plan): RedirectResponse
    {
        // الدفع الفعلي خارج نطاق هذا الإصدار: التفعيل يمنح رصيد الخطة مباشرة.
        // تكامل بوابة الدفع يستبدل هذه النقطة لاحقًا دون تغيير بقية النظام.
        $workspace = $request->user()->primaryWorkspace();
        $this->subscriptions->subscribe($workspace, $plan);

        return redirect()->route('app.billing')
            ->with('status', "فُعّلت خطة «{$plan->name}» وأُضيف رصيدها إلى محفظتك.");
    }
}
