<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 100), 1), 100);
        $status = trim((string) $request->query('status', ''));

        $payments = Payment::with(['workspace.owner', 'creditPack', 'plan'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'user' => $payment->workspace->owner?->only(['id', 'name', 'email']),
                'provider' => $payment->provider,
                'purpose' => $payment->purpose,
                'item' => $payment->plan?->name ?? $payment->creditPack?->name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'charged_amount' => $payment->charged_amount,
                'charged_currency' => $payment->charged_currency,
                'credits_granted' => $payment->credits_granted,
                'status' => $payment->status,
                'status_label' => $payment->statusLabel(),
                'failure_reason' => $payment->failure_reason,
                'awaiting_approval' => $payment->awaitsApproval(),
                'created_at' => $payment->created_at?->toISOString(),
            ])->all();

        return response()->json([
            'data' => $payments,
            'meta' => ['limit' => $limit, 'status' => $status],
        ]);
    }

    public function approve(
        Request $request,
        Payment $payment,
        AdminPaymentController $payments,
    ): JsonResponse {
        $this->confirm($request);

        if (! $payment->awaitsApproval()) {
            return response()->json(['message' => 'هذه الدفعة عولجت مسبقاً.'], 409);
        }

        $payments->approve($request, $payment);

        return response()->json(['data' => $payment->fresh()->only([
            'id', 'status', 'credits_granted', 'approved_by', 'approved_at',
        ])]);
    }

    public function reject(
        Request $request,
        Payment $payment,
        AdminPaymentController $payments,
    ): JsonResponse {
        $this->confirm($request);

        if (! $payment->awaitsApproval()) {
            return response()->json(['message' => 'هذه الدفعة عولجت مسبقاً.'], 409);
        }

        $payments->reject($request, $payment);

        return response()->json(['data' => $payment->fresh()->only([
            'id', 'status', 'failure_reason',
        ])]);
    }

    private function confirm(Request $request): void
    {
        $request->validate(['confirmation' => ['required', 'accepted']]);
    }
}
