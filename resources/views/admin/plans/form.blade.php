@extends('layouts.admin', ['title' => 'الخطة', 'pageTitle' => $plan->exists ? 'تعديل الخطة' : 'إنشاء خطة', 'pageKicker' => 'Plans'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>بيانات الخطة</h2>
        </div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>الكود</span>
                <input class="admin-input" name="code" value="{{ old('code', $plan->code) }}">
            </label>
            <label class="admin-field">
                <span>الاسم العربي</span>
                <input class="admin-input" name="name_ar" value="{{ old('name_ar', $plan->name_ar) }}">
            </label>
            <label class="admin-field">
                <span>الاسم الإنجليزي</span>
                <input class="admin-input" name="name_en" value="{{ old('name_en', $plan->name_en) }}">
            </label>
            <label class="admin-field">
                <span>السعر الشهري</span>
                <input class="admin-input" type="number" step="0.01" name="monthly_price" value="{{ old('monthly_price', $plan->monthly_price) }}">
            </label>
            <label class="admin-field">
                <span>السعر السنوي (عرض/تقدير)</span>
                <input class="admin-input" type="number" step="0.01" name="annual_price" value="{{ old('annual_price', $plan->annual_price) }}" placeholder="اختياري">
            </label>
            <label class="admin-field">
                <span>PayPal Plan ID — شهري</span>
                <input class="admin-input" name="paypal_plan_id_monthly" value="{{ old('paypal_plan_id_monthly', $plan->paypal_plan_id_monthly) }}" placeholder="P-xxxxx">
                @if (filled($plan->code))
                    <span class="text-caption" style="display:block;margin-top:0.35rem;">بديل في .env: <code>PAYPAL_PLAN_{{ strtoupper($plan->code) }}_MONTHLY</code>@if (in_array($plan->code, ['starter', 'team'], true)) — أو اتركه فارغاً واضبط <code>PAYPAL_PLAN_PRO_MONTHLY</code> فقط@endif @if ($plan->code === 'agency') — أو <code>PAYPAL_PLAN_ENT_MONTHLY</code>@endif</span>
                @endif
            </label>
            <label class="admin-field">
                <span>PayPal Plan ID — سنوي</span>
                <input class="admin-input" name="paypal_plan_id_annual" value="{{ old('paypal_plan_id_annual', $plan->paypal_plan_id_annual) }}" placeholder="P-xxxxx">
                @if (filled($plan->code))
                    <span class="text-caption" style="display:block;margin-top:0.35rem;">بديل في .env: <code>PAYPAL_PLAN_{{ strtoupper($plan->code) }}_ANNUAL</code>@if (in_array($plan->code, ['starter', 'team'], true)) — أو <code>PAYPAL_PLAN_PRO_ANNUAL</code>@endif @if ($plan->code === 'agency') — أو <code>PAYPAL_PLAN_ENT_ANNUAL</code>@endif</span>
                @endif
            </label>
            <label class="admin-field">
                <span>الحالة</span>
                <select class="admin-input" name="status">
                    @foreach (['active', 'inactive', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $plan->status?->value ?? $plan->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>Entitlements الافتراضية</h2>
            <button type="button" class="btn btn-secondary btn-sm" data-dynamic-list-add="plan-entitlements" data-template-id="plan-entitlements-template">إضافة سطر</button>
        </div>

        @php
            $rows = old('entitlements', $entitlements->map(fn ($item) => [
                'key' => $item->key,
                'value_type' => $item->value_type,
                'value' => is_array($item->value) ? json_encode($item->value['value'] ?? $item->value, JSON_UNESCAPED_UNICODE) : $item->decodedValue(),
            ])->all() ?: [['key' => '', 'value_type' => 'boolean', 'value' => 'true']]);
        @endphp
        <div class="admin-dynamic-list" id="plan-entitlements" data-next-index="{{ count($rows) }}">
            @foreach ($rows as $index => $row)
                <div class="admin-dynamic-row">
                    <input class="admin-input" name="entitlements[{{ $index }}][key]" value="{{ $row['key'] }}">
                    <select class="admin-input" name="entitlements[{{ $index }}][value_type]">
                        @foreach (['boolean', 'integer', 'float', 'string', 'json'] as $type)
                            <option value="{{ $type }}" @selected(($row['value_type'] ?? 'boolean') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    <input class="admin-input" name="entitlements[{{ $index }}][value]" value="{{ is_array($row) ? $row['value'] : '' }}">
                    <button type="button" class="btn btn-ghost btn-sm" data-dynamic-remove>حذف</button>
                </div>
            @endforeach
        </div>

        <template id="plan-entitlements-template">
            <div class="admin-dynamic-row">
                <input class="admin-input" name="entitlements[__INDEX__][key]" value="">
                <select class="admin-input" name="entitlements[__INDEX__][value_type]">
                    <option value="boolean">boolean</option>
                    <option value="integer">integer</option>
                    <option value="float">float</option>
                    <option value="string">string</option>
                    <option value="json">json</option>
                </select>
                <input class="admin-input" name="entitlements[__INDEX__][value]" value="">
                <button type="button" class="btn btn-ghost btn-sm" data-dynamic-remove>حذف</button>
            </div>
        </template>

        <div class="admin-help mt-4">
            <p>الموديولات المرجعية الحالية:</p>
            <p>{{ collect($modules)->keys()->implode(' | ') }}</p>
            <p class="mt-4">لعرض معرّفات الخطط من PayPal على الخادم: <code>php artisan paypal:list-plans</code></p>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $plan->exists ? 'حفظ التعديلات' : 'إنشاء الخطة' }}</button>
        <a href="{{ route('admin.plans.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection
