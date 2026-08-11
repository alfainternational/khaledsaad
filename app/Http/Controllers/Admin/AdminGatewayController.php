<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'currency' => 'nullable|string|size:3',
            'fx_rate' => 'nullable|numeric|min:0',
            'instructions' => 'nullable|string|max:2000',
        ]);

        $data = $this->normalise($data);

        $gateway = PaymentGateway::create([
            ...$data,
            'is_active' => false,
            'credentials' => $this->credentialsFrom($request, $data['provider']),
        ]);

        return redirect()->route('admin.gateways.edit', $gateway)->with('status', __('أُنشئت البوابة وحُفظت بيانات الربط. اختبر الاتصال ثم فعّلها.'));
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
        $data = $this->normalise($request->validate([
            'label' => 'required|string|max:120',
            'mode' => 'required|in:test,live',
            'currency' => 'nullable|string|size:3',
            'fx_rate' => 'nullable|numeric|min:0',
            'instructions' => 'nullable|string|max:2000',
        ]));

        // المفاتيح الفارغة تُبقي القيمة السابقة (لا تُعرض بعد الحفظ لأمانها).
        $incoming = $this->credentialsFrom($request, $gateway->provider);
        $merged = array_merge($gateway->credentials ?? [], array_filter($incoming, fn ($v) => $v !== null && $v !== ''));

        $credentialsChanged = $merged !== ($gateway->credentials ?? []);
        $gateway->update([
            ...$data,
            'credentials' => $merged,
            ...($credentialsChanged ? [
                'health_status' => null,
                'last_health_check_at' => null,
                'last_health_message' => null,
            ] : []),
        ]);

        return redirect()->route('admin.gateways.index')->with('status', __('حُدّثت البوابة.'));
    }

    public function toggle(PaymentGateway $gateway): RedirectResponse
    {
        // المفاتيح الإلزامية شرط التفعيل: بوابة ناقصة تعني شراءً ينفجر عند أول عميل.
        if (! $gateway->is_active && ! $gateway->hasRequiredCredentials()) {
            return back()->withErrors(['gateway' => __('أضف كل المفاتيح الإلزامية قبل تفعيل البوابة.')]);
        }

        if (! $gateway->is_active && $gateway->isLive() && ! $gateway->isHealthy()) {
            return back()->withErrors(['gateway' => __('اختبر اتصال البوابة بنجاح قبل تفعيلها في الوضع المباشر.')]);
        }

        $gateway->update(['is_active' => ! $gateway->is_active]);

        if (! $gateway->is_active && $gateway->is_default) {
            $gateway->update(['is_default' => false]);
            PaymentGateway::where('is_active', true)->orderBy('sort_order')->first()?->update(['is_default' => true]);
        }

        return back()->with('status', $gateway->is_active ? __('فُعّلت البوابة.') : __('عُطّلت البوابة.'));
    }

    public function destroy(PaymentGateway $gateway): RedirectResponse
    {
        if (Payment::where('payment_gateway_id', $gateway->id)->exists()) {
            return back()->withErrors(['gateway' => __('لا يمكن حذف بوابة مرتبطة بمدفوعات سابقة. عطّلها بدلًا من ذلك.')]);
        }

        $gateway->delete();

        return redirect()->route('admin.gateways.index')->with('status', __('حُذفت البوابة.'));
    }

    public function test(PaymentGateway $gateway, PaymentGatewayManager $manager): RedirectResponse
    {
        if (! $gateway->hasRequiredCredentials()) {
            return back()->withErrors(['gateway' => __('أضف كل بيانات الربط الإلزامية أولًا.')]);
        }

        try {
            $health = $manager->provider($gateway)->healthCheck();
            $gateway->update([
                'health_status' => $health->healthy ? 'healthy' : 'unhealthy',
                'last_health_check_at' => now(),
                'last_health_message' => $health->message,
            ]);

            return back()->with($health->healthy ? 'status' : 'error', $health->message);
        } catch (\Throwable $exception) {
            report($exception);
            $gateway->update([
                'health_status' => 'unhealthy',
                'last_health_check_at' => now(),
                'last_health_message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['gateway' => __('فشل اختبار الاتصال: :reason', ['reason' => $exception->getMessage()])]);
        }
    }

    public function setDefault(PaymentGateway $gateway): RedirectResponse
    {
        if (! $gateway->is_active || ! $gateway->hasRequiredCredentials()) {
            return back()->withErrors(['gateway' => __('فعّل البوابة المهيأة قبل جعلها افتراضية.')]);
        }

        DB::transaction(function () use ($gateway): void {
            PaymentGateway::where('id', '!=', $gateway->id)->update(['is_default' => false]);
            $gateway->update(['is_default' => true]);
        });

        return back()->with('status', __('أصبحت البوابة هي الافتراضية، مع بقاء البوابات الأخرى متاحة للعملاء.'));
    }

    /**
     * توحيد شكل القيم: العملة بأحرف كبيرة، والمعامل رقم موجب.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        $data['currency'] = filled($data['currency'] ?? null) ? strtoupper($data['currency']) : null;
        $data['fx_rate'] = (float) ($data['fx_rate'] ?? 1) ?: 1;

        return $data;
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
