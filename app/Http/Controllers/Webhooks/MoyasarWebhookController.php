<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoyasarWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayManager $gateways, CheckoutService $checkout): JsonResponse
    {
        $gateway = $gateways->gatewayFor('moyasar');
        if ($gateway === null) {
            return response()->json(['ignored' => 'no_gateway'], 202);
        }

        $payload = $request->all();
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $eventId = (string) ($payload['event_id'] ?? $hash);
        $event = PaymentWebhookEvent::firstOrCreate(
            ['provider' => 'moyasar', 'event_id' => $eventId],
            [
                'payment_gateway_id' => $gateway->id,
                'event_type' => (string) ($payload['status'] ?? 'unknown'),
                'payload_hash' => $hash,
            ],
        );

        if (! $event->wasRecentlyCreated) {
            return response()->json(['duplicate' => true]);
        }

        $paymentId = $payload['metadata']['payment_id'] ?? null;
        $payment = ctype_digit((string) $paymentId)
            ? Payment::find((int) $paymentId)
            : Payment::where('external_id', $payload['id'] ?? '')->first();

        if ($payment === null || $payment->provider !== 'moyasar') {
            $event->update(['status' => 'ignored', 'error' => 'unknown_payment', 'processed_at' => now()]);

            return response()->json(['ignored' => 'unknown_payment'], 202);
        }

        $paid = $checkout->complete($payment, $payload);
        $event->update([
            'status' => $paid ? 'processed' : 'rejected',
            'error' => $paid ? null : 'server_verification_failed',
            'processed_at' => now(),
        ]);

        return response()->json(['handled' => true, 'paid' => $paid]);
    }
}
