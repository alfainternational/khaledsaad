<?php

namespace App\Http\Controllers;

use App\Application\Billing\ProcessPayPalWebhookAction;
use App\Services\Billing\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function handle(
        Request $request,
        PayPalService $paypal,
        ProcessPayPalWebhookAction $process,
    ): JsonResponse {
        if (! $paypal->isConfigured()) {
            return response()->json(['error' => 'PayPal غير مفعّل'], 503);
        }

        $body = $request->getContent();
        $headers = array_change_key_case($request->headers->all(), CASE_LOWER);
        $headers = array_map(fn ($v) => is_array($v) ? $v[0] : $v, $headers);

        if (! $paypal->verifyWebhook($headers, $body)) {
            Log::warning('PayPal Webhook: توقيع غير صالح');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $request->json()->all();
        $eventId = isset($event['id']) ? (string) $event['id'] : null;
        $eventType = isset($event['event_type']) ? (string) $event['event_type'] : '';
        $resourceId = isset($event['resource']['id']) ? (string) $event['resource']['id'] : null;

        if ($eventId === null || $eventId === '') {
            return response()->json(['ok' => true]);
        }

        if ($paypal->isEventProcessed($eventId)) {
            return response()->json(['ok' => 'already_processed']);
        }

        $paypal->recordEvent($eventId, $eventType, $resourceId, $event);

        try {
            $process->handle($eventType, $event);
        } catch (\Throwable $e) {
            Log::error('PayPal Webhook: فشل المعالجة', ['e' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
