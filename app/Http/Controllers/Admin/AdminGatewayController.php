<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إدارة بوابات الدفع بمفاتيحها من الواجهة.
 * المفاتيح تُخزَّن مشفّرة ولا تُعرض بعد الحفظ (تُترك فارغة = بلا تغيير).
 */
class AdminGatewayController extends Controller
{
    public function index(): View
    {
        return view('admin.gateways.index', [
            'gateways' => PaymentGateway::orderBy('sort_order')->get(),
            'catalogue' => PaymentGatewayManager::catalogue(),
        ]);
    }

    public function create(): View
    {
        return view('admin.gateways.form', [
            'gateway' => new PaymentGateway(['mode' => 'test']),
            'catalogue' => PaymentGatewayManager::catalogue(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|in:'.implode(',', array_keys(PaymentGatewayManager::catalogue())).'|unique:payment_gateways,provider',
            'label' => 'required|string|max:120',
            'mode' => 'required|in:test,live',
        ]);

        $gateway = PaymentGateway::create([
            ...$data,
            'is_active' => false,
            'credentials' => $this->credentialsFrom($request, $data['provider']),
        ]);

        return redirect()->route('admin.gateways.edit', $gateway)->with('status', 'أُنشئت البوابة. أضف المفاتيح ثم فعّلها.');
    }

    public function edit(PaymentGateway $gateway): View
    {
        return view('admin.gateways.form', [
            'gateway' => $gateway,
            'catalogue' => PaymentGatewayManager::catalogue(),
        ]);
    }

    public function update(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'mode' => 'required|in:test,live',
        ]);

        // المفاتيح الفارغة تُبقي القيمة السابقة (لا تُعرض بعد الحفظ لأمانها).
        $incoming = $this->credentialsFrom($request, $gateway->provider);
        $merged = array_merge($gateway->credentials ?? [], array_filter($incoming, fn ($v) => $v !== null && $v !== ''));

        $gateway->update([...$data, 'credentials' => $merged]);

        return redirect()->route('admin.gateways.index')->with('status', 'حُدّثت البوابة.');
    }

    public function toggle(PaymentGateway $gateway): RedirectResponse
    {
        if (! $gateway->is_active && ! $gateway->isConfigured()) {
            // isConfigured يتطلب is_active؛ نتحقق من المفاتيح فقط قبل التفعيل.
            if (empty(array_filter($gateway->credentials ?? [])) && $gateway->provider !== 'manual') {
                return back()->withErrors(['gateway' => 'أضف مفاتيح البوابة قبل تفعيلها.']);
            }
        }

        // بوابة واحدة مفعّلة في كل وقت: تفعيل واحدة يعطّل البقية.
        if (! $gateway->is_active) {
            PaymentGateway::where('id', '!=', $gateway->id)->update(['is_active' => false]);
        }

        $gateway->update(['is_active' => ! $gateway->is_active]);

        return back()->with('status', $gateway->is_active ? 'فُعّلت البوابة.' : 'عُطّلت البوابة.');
    }

    public function destroy(PaymentGateway $gateway): RedirectResponse
    {
        $gateway->delete();

        return redirect()->route('admin.gateways.index')->with('status', 'حُذفت البوابة.');
    }

    /**
     * @return array<string, string>
     */
    private function credentialsFrom(Request $request, string $provider): array
    {
        $fields = PaymentGatewayManager::catalogue()[$provider]['fields'] ?? [];

        return collect($fields)
            ->mapWithKeys(fn ($field) => [$field => (string) $request->input("credentials.{$field}", '')])
            ->all();
    }
}
