@extends('layouts.app')
@section('layout', 'form')

@section('title', $gateway->exists ? 'تعديل بوابة' : 'بوابة جديدة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · بوابات الدفع</p>
            <h1>{{ $gateway->exists ? 'تعديل: '.$gateway->label : 'بوابة جديدة' }}</h1>
        </div>
        <a href="{{ route('admin.gateways.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ $gateway->exists ? route('admin.gateways.update', $gateway) : route('admin.gateways.store') }}" class="form form--wide form-layout">
        @csrf
        @if ($gateway->exists) @method('PUT') @endif

        @unless ($gateway->exists)
            <label class="field">
                <span class="field__label">المزوّد</span>
                <select name="provider" id="provider-select" required>
                    @foreach ($catalogue as $key => $meta)
                        <option value="{{ $key }}" @selected(old('provider') === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endunless

        <label class="field">
            <span class="field__label">الاسم الظاهر</span>
            <input type="text" name="label" value="{{ old('label', $gateway->label) }}" required>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">الوضع</span>
                <select name="mode">
                    <option value="test" @selected(old('mode', $gateway->mode) === 'test')>اختبار (Sandbox)</option>
                    <option value="live" @selected(old('mode', $gateway->mode) === 'live')>مباشر (Live)</option>
                </select>
            </label>
            <label class="field">
                <span class="field__label">عملة التحصيل</span>
                <input type="text" name="currency" maxlength="3" style="text-transform:uppercase"
                    value="{{ old('currency', $gateway->currency) }}" placeholder="USD">
                <span class="field__help">PayPal لا يقبل SAR. اتركها فارغة لتحصيل بعملة السعر كما هي.</span>
            </label>
            <label class="field">
                <span class="field__label">معامل التحويل من {{ config('billing.currency', 'SAR') }}</span>
                <input type="number" step="0.000001" min="0" name="fx_rate"
                    value="{{ old('fx_rate', $gateway->fx_rate ?: 1) }}">
                <span class="field__help">مثال: 0.2667 يعني أن سعر 100 يُحصَّل 26.67 بعملة البوابة.</span>
            </label>
        </div>

        @foreach ($catalogue as $providerKey => $providerMeta)
            @if (! empty($providerMeta['fields']))
            <fieldset class="field gateway-credentials" data-provider="{{ $providerKey }}"
                @if (($gateway->exists ? $gateway->provider : old('provider', 'paypal')) !== $providerKey) hidden @endif>
                <legend class="field__label">بيانات ربط {{ $providerMeta['label'] }}</legend>
                <p class="field__help">اتركها فارغة للإبقاء على المفاتيح الحالية (لا تُعرض بعد الحفظ لأمانها).</p>
                @foreach ($providerMeta['fields'] as $credKey)
                    <label class="field">
                        <span class="field__label">
                            {{ $credKey }}
                            @if (in_array($credKey, $providerMeta['required'] ?? [], true))
                                <span class="badge">إلزامي</span>
                            @endif
                        </span>
                        <input type="password" name="credentials[{{ $credKey }}]" autocomplete="off"
                            @disabled($gateway->exists && $gateway->provider !== $providerKey)
                            placeholder="{{ $gateway->exists && $gateway->provider === $providerKey && $gateway->credential($credKey) ? '•••••• (محفوظ)' : 'غير مضبوط' }}">
                    </label>
                @endforeach
            </fieldset>
            @endif
        @endforeach

        @if ($gateway->exists && ! empty($catalogue[$gateway->provider]['hint']))
            <p class="field__help">{{ $catalogue[$gateway->provider]['hint'] }}</p>
        @endif

        @if ($gateway->exists && $gateway->provider === 'paypal')
            <label class="field">
                <span class="field__label">رابط الإشعار (سجّله في PayPal Developer)</span>
                <input type="text" value="{{ route('webhooks.paypal') }}" readonly onclick="this.select()">
                <span class="field__help">
                    سجّل هذا الرابط لأحداث PAYMENT.CAPTURE.COMPLETED و.DENIED و.REFUNDED، ثم ضع webhook_id أعلاه.
                    بدونه يصل الرصيد فقط عند عودة العميل للموقع.
                </span>
            </label>
        @endif

        <label class="field">
            <span class="field__label">تعليمات الدفع (تظهر للعميل)</span>
            <textarea name="instructions" rows="3" placeholder="{{ __('بيانات الحساب البنكي أو ملاحظة للعميل') }}">{{ old('instructions', $gateway->instructions) }}</textarea>
        </label>

        <button type="submit" class="btn btn--primary">{{ $gateway->exists ? 'حفظ' : 'إنشاء وحفظ بيانات الربط' }}</button>
    </form>

    @unless ($gateway->exists)
        <script>
            (() => {
                const select = document.getElementById('provider-select');
                const sync = () => document.querySelectorAll('.gateway-credentials').forEach((box) => {
                    const active = box.dataset.provider === select.value;
                    box.hidden = !active;
                    box.querySelectorAll('input').forEach((input) => input.disabled = !active);
                });
                select.addEventListener('change', sync);
                sync();
            })();
        </script>
    @endunless
@endsection
