<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Report;
use App\Services\Billing\CreditManager;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionManager;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Reports\ReportPdfGenerator;
use App\Support\Billing\FeatureKey;
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
        private readonly Entitlements $entitlements,
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
                'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
                'scheduled_plan' => $subscription->scheduledPlan?->only(['key', 'name']),
                'payments_enabled' => $this->gateways->hasActiveGateway(),
                'gateways' => $this->gateways->activeGateways()->map(fn ($gateway) => [
                    'id' => $gateway->id,
                    'provider' => $gateway->provider,
                    'label' => $gateway->label,
                    'instructions' => $gateway->instructions,
                    'is_default' => $gateway->is_default,
                ])->all(),
                // ما يستحقه العميل الآن — يقرأه التطبيق ليخفي ما لا تسمح به خطته
                // بدل أن يعرض زرًّا يُرفض عند الضغط.
                'entitlements' => collect($this->entitlements->for($workspace))
                    ->map(fn (array $entry) => [
                        'enabled' => $entry['enabled'],
                        'value' => $entry['value'],
                        'name' => $entry['feature']->name,
                    ])->all(),
                'plans' => Plan::where('is_public', true)->with('planFeatures')->orderBy('sort_order')->get()
                    ->map(fn (Plan $plan) => [
                        'key' => $plan->key,
                        'name' => $plan->name,
                        'price' => $plan->price,
                        'monthly_credits' => $plan->monthly_credits,
                        'project_limit' => $plan->project_limit,
                        'features' => $this->entitlements->displayFeatures($plan),
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
                'payments' => Payment::where('workspace_id', $workspace->id)->latest('id')->limit(20)->get()
                    ->map(fn (Payment $payment) => [
                        'id' => $payment->id,
                        'provider' => $payment->provider,
                        'purpose' => $payment->purpose,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'status_label' => $payment->statusLabel(),
                        'refunded_amount' => $payment->refunded_amount,
                        'created_at' => $payment->created_at?->toISOString(),
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
                'message' => 'هذه خطة مدفوعة. اخترها من صفحة الخطط ثم أكمل الدفع.',
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

        return $this->beginCheckout($request, fn ($workspace, $urls, $gateway) => $this->checkout->startPlanPurchase($workspace, $plan, $urls, $gateway));
    }

    public function checkoutPack(Request $request, CreditPack $pack): JsonResponse
    {
        return $this->beginCheckout($request, fn ($workspace, $urls, $gateway) => $this->checkout->startCreditPackPurchase($workspace, $pack, $urls, $gateway));
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
            return response()->json(['message' => 'الدفع الإلكتروني غير متاح حاليًا. حاول مرة أخرى لاحقًا.'], 422);
        }

        $workspace = $request->user()->primaryWorkspace();
        $gateway = $request->filled('gateway_id')
            ? $this->gateways->activeGatewayById($request->integer('gateway_id'))
            : $this->gateways->defaultGateway();

        if ($gateway === null) {
            return response()->json(['message' => 'وسيلة الدفع المختارة غير متاحة.'], 422);
        }

        $urls = fn (Payment $payment) => [
            route('api.v1.checkout.callback', $payment),
            route('app.billing'),
        ];

        try {
            ['payment' => $payment, 'session' => $session] = $starter($workspace, $urls, $gateway);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // بوابة بلا تحويل: إما اعتماد فوري، وإما انتظار تأكيد الآدمن.
        if (! $session->requiresRedirect) {
            if ($session->pendingApproval) {
                return response()->json(['data' => [
                    'completed' => false,
                    'pending_approval' => true,
                    'message' => $session->message ?? 'سجّلنا طلبك. سيُعتمد رصيدك فور تأكيد التحويل.',
                ]]);
            }

            $paid = $this->checkout->complete($payment, []);

            return response()->json(['data' => [
                'completed' => $paid,
                'message' => $paid ? 'تم اعتماد الدفع وأُضيف رصيدك.' : 'سجّلنا طلبك وهو قيد المراجعة.',
            ]]);
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

    public function checkoutCancel(Request $request, Payment $payment): JsonResponse
    {
        abort_unless(
            $payment->workspace()->where('owner_id', $request->user()?->id)->exists(),
            404,
        );

        $this->checkout->cancel($payment);

        return response()->json(['data' => [
            'cancelled' => $payment->fresh()->status === Payment::STATUS_CANCELLED,
            'status' => $payment->fresh()->status,
        ]]);
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

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => [
            'read' => true,
            'unread' => 0,
        ]]);
    }

    public function reportPdf(Request $request, Report $report): StreamedResponse|JsonResponse
    {
        abort_unless(
            $report->project->workspace()->where('owner_id', $request->user()->id)->exists(),
            404,
        );

        // نفس ترتيب الويب: الملكية أولًا، ثم الاستحقاق.
        if (! $this->entitlements->allows($report->project->workspace, FeatureKey::REPORTS_PDF)) {
            return response()->json([
                'message' => 'تصدير PDF غير متاح في خطتك الحالية.',
                'feature' => FeatureKey::REPORTS_PDF,
            ], 403);
        }

        return $this->pdf->download($report);
    }
}
