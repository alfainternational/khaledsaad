<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\View\View;

class AdminPaymentController extends Controller
{
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
                    'credits' => $payment->credits_granted,
                    'status' => $payment->statusLabel(),
                    'at' => $payment->created_at->translatedFormat('j F Y'),
                ]),
            'totals' => [
                'paid' => Payment::where('status', 'paid')->sum('amount'),
                'count' => Payment::where('status', 'paid')->count(),
            ],
        ]);
    }
}
