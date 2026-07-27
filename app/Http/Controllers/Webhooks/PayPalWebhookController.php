<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PayPalProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * إشعار PayPal — المسار الذي يجعل الدفع موثوقًا.
 *
 * الاعتماد على عودة المتصفح وحدها يعني ضياع الرصيد كلما أغلق العميل الصفحة
 * بعد الدفع. هنا يخبرنا PayPal بنفسه، ونتحقق من توقيعه قبل تصديقه.
 *
 * نُعيد 200 دائمًا لما فهمناه حتى لا يعيد PayPal الإرسال بلا نهاية،
 * و401 لما فشل توقيعه.
 */
class PayPalWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentGatewayManager $gateways,
        CheckoutService $checkout,
    ): JsonResponse {
        $gateway = $gateways->gatewayFor('paypal');

        if ($gateway === null) {
            return response()->json(['ignored' => 'no_gateway'], 202);
        }

        $provider = $gateways->provider($gateway);

        if (! $provider instanceof PayPalProvider) {
            return response()->json(['ignored' => 'not_paypal'], 202);
        }

        $event = $request->all();
        $headers = collect($request->headers->all())
            ->map(fn (array $values) => (string) ($values[0] ?? ''))
            ->all();

        if (! $provider->verifyWebhook($headers, $event)) {
            Log::warning('PayPal webhook signature rejected', ['type' => $event['event_type'] ?? null]);

            return response()->json(['message' => 'signature_invalid'], 401);
        }

        $payment = $this->resolvePayment($event);

        if ($payment === null) {
            return response()->json(['ignored' => 'unknown_payment'], 202);
        }

        // الالتقاط المكتمل هو الحدث الوحيد الذي يمنح. ما عداه يُسجَّل ويُتجاهل.
        $type = $event['event_type'] ?? '';

        if (in_array($type, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true)) {
            $checkout->complete($payment, $event);

            return response()->json(['handled' => $type]);
        }

        if (in_array($type, ['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.REFUNDED', 'CHECKOUT.ORDER.VOIDED'], true)) {
            $checkout->reject($payment, strtolower($type));

            return response()->json(['handled' => $type]);
        }

        return response()->json(['ignored' => $type], 202);
    }

    /**
     * ربط الحدث بدفعتنا: custom_id الذي أرسلناه، أو معرّف الطلب المحفوظ.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolvePayment(array $event): ?Payment
    {
        $resource = $event['resource'] ?? [];

        $customId = $resource['custom_id']
            ?? ($resource['purchase_units'][0]['custom_id'] ?? null);

        if ($customId !== null && ctype_digit((string) $customId)) {
            return Payment::find((int) $customId);
        }

        // طلب الشراء (order) أو معرّفه داخل روابط الالتقاط.
        $orderId = $resource['id']
            ?? ($resource['supplementary_data']['related_ids']['order_id'] ?? null);

        return $orderId !== null
            ? Payment::where('external_id', $orderId)->first()
            : null;
    }
}
