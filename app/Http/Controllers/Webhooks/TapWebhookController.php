<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\TapProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TapWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayManager $gateways, CheckoutService $checkout): JsonResponse
    {
        $gateway = $gateways->gatewayFor('tap');
        $provider = $gateway ? $gateways->provider($gateway) : null;
        $payload = $request->all();

        if (! $provider instanceof TapProvider || ! $provider->verifyWebhook(
            $payload,
            $request->header('hashstring') ?: ($payload['hashstring'] ?? null),
        )) {
            return response()->json(['message' => 'signature_invalid'], 401);
        }

        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $event = PaymentWebhookEvent::firstOrCreate(
            ['provider' => 'tap', 'event_id' => (string) ($payload['event_id'] ?? $hash)],
            [
                'payment_gateway_id' => $gateway->id,
                'event_type' => (string) ($payload['status'] ?? 'unknown'),
                'payload_hash' => $hash,
            ],
        );
        if (! $event->wasRecentlyCreated) {
            return response()->json(['duplicate' => true]);
        }

        $paymentId = $payload['reference']['order'] ?? $payload['metadata']['payment_id'] ?? null;
        $payment = ctype_digit((string) $paymentId) ? Payment::find((int) $paymentId) : null;
        if ($payment === null || $payment->provider !== 'tap') {
            $event->update(['status' => 'ignored', 'error' => 'unknown_payment', 'processed_at' => now()]);

            return response()->json(['ignored' => 'unknown_payment'], 202);
        }

        $paid = $checkout->complete($payment, $payload);
        $event->update(['status' => $paid ? 'processed' : 'rejected', 'processed_at' => now()]);

        return response()->json(['handled' => true, 'paid' => $paid]);
    }
}
