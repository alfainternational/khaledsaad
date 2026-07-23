<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Report;
use App\Services\Billing\CreditManager;
use App\Services\Billing\SubscriptionManager;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Reports\ReportPdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * نظير التطبيق لصفحات الأرصدة والإشعارات وتنزيل PDF.
 * كل نقطة تستخدم نفس الخدمة التي يستخدمها الويب.
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly CreditManager $credits,
        private readonly ReportPdfGenerator $pdf,
        private readonly CheckoutService $checkout,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function billing(Request $request): JsonResponse
    {
        $workspace = $request->user()->primaryWorkspace();
        $subscription = $this->subscriptions->ensure($workspace);
        $wallet = $this->credits->walletFor($workspace);

        return response()->json([
            'data' => [
                'balance' => $wallet->balance,
                'current_plan' => $subscription->plan->key,
                'project_count' => $workspace->projects()->count(),
                'project_limit' => $subscription->plan->project_limit,
                'payments_enabled' => $this->gateways->hasActiveGateway(),
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
                'packs' => CreditPack::active()->orderBy('sort_order')->get()
                    ->map(fn ($pack) => [
                        'id' => $pack->id,
                        'name' => $pack->name,
                        'credits' => $pack->credits,
                        'price' => $pack->price,
                        'currency' => $pack->currency,
                    ])->all(),
            ],
        ]);
    }

    /**
     * الخطة المجانية فقط تُفعَّل مباشرة. المدفوعة تمر بالبوابة كالويب تمامًا.
     */
    public function subscribe(Request $request, Plan $plan): JsonResponse
    {
        if ($plan->price > 0) {
            return response()->json([
                'message' => 'هذه خطة مدفوعة. استخدم مسار الشراء عبر البوابة.',
            ], 422);
        }

        $this->subscriptions->subscribe($request->user()->primaryWorkspace(), $plan);

        return response()->json(['data' => ['message' => "فُعّلت خطة {$plan->name}."]]);
    }

    /**
     * يبدأ شراء خطة مدفوعة: يعيد رابط الدفع ليفتحه التطبيق، أو يكتمل مباشرة
     * إن كانت البوابة يدوية/الخطة مجانية — نفس منطق CheckoutController في الويب.
     */
    public function checkoutPlan(Request $request, Plan $plan): JsonResponse
    {
        if ($plan->price === 0) {
            $this->subscriptions->subscribe($request->user()->primaryWorkspace(), $plan);

            return response()->json(['data' => ['completed' => true, 'message' => "فُعّلت خطة {$plan->name}."]]);
        }

        return $this->beginCheckout($request, fn ($workspace, $urls) => $this->checkout->startPlanPurchase($workspace, $plan, $urls));
    }

    public function checkoutPack(Request $request, CreditPack $pack): JsonResponse
    {
        return $this->beginCheckout($request, fn ($workspace, $urls) => $this->checkout->startCreditPackPurchase($workspace, $pack, $urls));
    }

    public function creditPacks(): JsonResponse
    {
        return response()->json([
            'data' => CreditPack::active()->orderBy('sort_order')->get()
                ->map(fn ($pack) => [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'credits' => $pack->credits,
                    'price' => $pack->price,
                    'currency' => $pack->currency,
                ])->all(),
            'payments_enabled' => $this->gateways->hasActiveGateway(),
        ]);
    }

    private function beginCheckout(Request $request, callable $starter): JsonResponse
    {
        if (! $this->gateways->hasActiveGateway()) {
            return response()->json(['message' => 'لا توجد بوابة دفع مفعّلة حاليًا.'], 422);
        }

        $workspace = $request->user()->primaryWorkspace();

        $urls = fn (Payment $payment) => [
            route('api.v1.checkout.callback', $payment),
            route('app.billing'),
        ];

        try {
            ['payment' => $payment, 'session' => $session] = $starter($workspace, $urls);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // البوابة اليدوية تكتمل مباشرة دون رابط خارجي.
        if (! $session->requiresRedirect) {
            $this->checkout->complete($payment, []);

            return response()->json(['data' => ['completed' => true, 'message' => 'تم اعتماد الدفع وأُضيف رصيدك.']]);
        }

        return response()->json(['data' => ['completed' => false, 'redirect_url' => $session->redirectUrl]]);
    }

    public function checkoutCallback(Request $request, Payment $payment): JsonResponse
    {
        abort_unless(
            $payment->workspace()->where('owner_id', $request->user()?->id)->exists(),
            404,
        );

        $paid = $this->checkout->complete($payment, $request->all());

        return response()->json(['data' => ['paid' => $paid]]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->latest()->limit(50)->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'إشعار',
                    'body' => $notification->data['body'] ?? '',
                    'url' => $notification->data['url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'at' => $notification->created_at->toIso8601String(),
                ])->all(),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['data' => ['read' => true]]);
    }

    public function reportPdf(Request $request, Report $report): StreamedResponse
    {
        abort_unless(
            $report->project->workspace()->where('owner_id', $request->user()->id)->exists(),
            404,
        );

        return $this->pdf->download($report);
    }
}
