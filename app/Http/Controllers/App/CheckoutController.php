<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Billing\SubscriptionManager;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * تدفّق شراء الأرصدة/الخطط عبر البوابة المفعّلة.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function creditPack(Request $request, CreditPack $pack): RedirectResponse
    {
        return $this->start($request, fn ($workspace, $urls, $gateway) => $this->checkout->startCreditPackPurchase($workspace, $pack, $urls, $gateway));
    }

    public function plan(Request $request, Plan $plan): RedirectResponse
    {
        // الخطة المجانية تُفعَّل مباشرة بلا دفع.
        if ($plan->price === 0) {
            app(SubscriptionManager::class)
                ->subscribe($request->user()->primaryWorkspace(), $plan);

            return redirect()->route('app.billing')->with('status', "فُعّلت خطة «{$plan->name}».");
        }

        return $this->start($request, fn ($workspace, $urls, $gateway) => $this->checkout->startPlanPurchase($workspace, $plan, $urls, $gateway));
    }

    public function callback(Request $request, Payment $payment): RedirectResponse
    {
        $this->assertOwner($request, $payment);

        $paid = $this->checkout->complete($payment, $request->all());

        return redirect()->route('app.billing')->with(
            'status',
            $paid ? 'تم الدفع وأُضيف رصيدك.' : 'تعذّر تأكيد الدفع. لم يُخصم منك شيء.',
        );
    }

    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        $this->assertOwner($request, $payment);
        $this->checkout->cancel($payment);

        return redirect()->route('app.billing')->with('status', 'أُلغيت عملية الدفع.');
    }

    private function start(Request $request, callable $starter): RedirectResponse
    {
        if (! $this->gateways->hasActiveGateway()) {
            return redirect()->route('app.billing')
                ->withErrors(['gateway' => 'الدفع الإلكتروني غير متاح حاليًا. حاول مرة أخرى لاحقًا.']);
        }

        $workspace = $request->user()->primaryWorkspace();
        $gateway = $request->filled('gateway_id')
            ? $this->gateways->activeGatewayById($request->integer('gateway_id'))
            : $this->gateways->defaultGateway();

        if ($gateway === null) {
            return redirect()->route('app.billing')->withErrors([
                'gateway_id' => 'وسيلة الدفع المختارة غير متاحة. اختر وسيلة مفعّلة.',
            ]);
        }

        // بنّاء الروابط يستقبل الدفعة بعد إنشائها فيضمّن معرّفها الحقيقي.
        $urls = fn (Payment $payment) => [
            route('app.checkout.callback', $payment),
            route('app.checkout.cancel', $payment),
        ];

        try {
            ['payment' => $payment, 'session' => $session] = $starter($workspace, $urls, $gateway);
        } catch (RuntimeException $exception) {
            return redirect()->route('app.billing')->withErrors(['gateway' => $exception->getMessage()]);
        }

        // بوابة بلا تحويل خارجي (التحويل البنكي): الطلب يُسجَّل معلّقًا
        // ولا يُمنح شيء قبل اعتماد الآدمن.
        if (! $session->requiresRedirect) {
            if ($session->pendingApproval) {
                return redirect()->route('app.billing')->with(
                    'status',
                    $session->message ?? 'سجّلنا طلبك. سيُعتمد رصيدك فور تأكيد التحويل.',
                );
            }

            $paid = $this->checkout->complete($payment, []);

            return redirect()->route('app.billing')->with(
                'status',
                $paid ? 'تم اعتماد الدفع وأُضيف رصيدك.' : 'سجّلنا طلبك وهو قيد المراجعة.',
            );
        }

        return redirect()->away($session->redirectUrl);
    }

    private function assertOwner(Request $request, Payment $payment): void
    {
        abort_unless(
            $payment->workspace()->where('owner_id', $request->user()->id)->exists(),
            404,
        );
    }
}
