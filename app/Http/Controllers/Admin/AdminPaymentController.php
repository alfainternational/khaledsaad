<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminPaymentController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function index(): View
    {
        return view('admin.payments.index', [
            'payments' => Payment::with(['workspace.owner', 'creditPack', 'plan'])
                ->latest('id')->limit(100)->get()
                ->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'user' => $payment->workspace->owner?->name ?? '—',
                    'provider' => $payment->provider,
                    'purpose' => $payment->purpose === 'plan'
                        ? ('خطة: '.($payment->plan?->name ?? '—'))
                        : ('حزمة: '.($payment->creditPack?->name ?? '—')),
                    'amount' => $payment->amount.' '.$payment->currency,
                    // ما حُصّل فعلًا لدى البوابة قد يختلف عملةً عن سعرنا.
                    'charged' => $payment->charged_amount !== null
                        ? number_format((float) $payment->charged_amount, 2).' '.$payment->charged_currency
                        : '—',
                    'credits' => $payment->credits_granted,
                    'status' => $payment->statusLabel(),
                    'reason' => $payment->failure_reason,
                    'awaiting' => $payment->awaitsApproval(),
                    'refunded' => (float) $payment->refunded_amount,
                    'refundable' => $payment->isPaid()
                        ? max(0, (float) ($payment->charged_amount ?? $payment->amount) - (float) $payment->refunded_amount)
                        : 0,
                    'at' => $payment->created_at->translatedFormat('j F Y'),
                ]),
            'totals' => [
                'paid' => Payment::where('status', 'paid')->sum('amount'),
                'count' => Payment::where('status', 'paid')->count(),
                'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
            ],
        ]);
    }

    /**
     * اعتماد تحويل بنكي: هنا فقط يتحوّل «قال إنه حوّل» إلى رصيد فعلي.
     */
    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        if (! $payment->awaitsApproval()) {
            return back()->withErrors(['payment' => __('هذه الدفعة ليست بانتظار اعتماد يدوي.')]);
        }

        $this->checkout->approveManually($payment, $request->user());

        return back()->with('status', "اعتُمدت الدفعة #{$payment->id} وأُضيف ما تقابله.");
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $this->checkout->reject($payment, 'rejected_by_admin');

        return back()->with('status', "رُفضت الدفعة #{$payment->id}.");
    }

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->checkout->refund($payment, (float) $data['amount'], $request->user(), $data['reason']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', "تم استرداد {$data['amount']} من الدفعة #{$payment->id}.");
    }
}
