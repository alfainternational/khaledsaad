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
        return $this->start($request, fn ($workspace, $urls) => $this->checkout->startCreditPackPurchase($workspace, $pack, $urls));
    }

    public function plan(Request $request, Plan $plan): RedirectResponse
    {
        // الخطة المجانية تُفعَّل مباشرة بلا دفع.
        if ($plan->price === 0) {
            app(SubscriptionManager::class)
                ->subscribe($request->user()->primaryWorkspace(), $plan);

            return redirect()->route('app.billing')->with('status', "فُعّلت خطة «{$plan->name}».");
        }

        return $this->start($request, fn ($workspace, $urls) => $this->checkout->startPlanPurchase($workspace, $plan, $urls));
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
                ->withErrors(['gateway' => 'لا توجد بوابة دفع مفعّلة حاليًا. حاول لاحقًا.']);
        }

        $workspace = $request->user()->primaryWorkspace();

        // بنّاء الروابط يستقبل الدفعة بعد إنشائها فيضمّن معرّفها الحقيقي.
        $urls = fn (Payment $payment) => [
            route('app.checkout.callback', $payment),
            route('app.checkout.cancel', $payment),
        ];

        try {
            ['payment' => $payment, 'session' => $session] = $starter($workspace, $urls);
        } catch (RuntimeException $exception) {
            return redirect()->route('app.billing')->withErrors(['gateway' => $exception->getMessage()]);
        }

        // البوابة اليدوية لا تحوّل: نعتمد الدفع مباشرة.
        if (! $session->requiresRedirect) {
            $this->checkout->complete($payment, []);

            return redirect()->route('app.billing')->with('status', 'تم اعتماد الدفع وأُضيف رصيدك.');
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
