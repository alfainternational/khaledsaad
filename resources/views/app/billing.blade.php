@extends('layouts.app', ['title' => 'الخطط والدفع', 'pageTitle' => 'الخطط والدفع', 'pageKicker' => 'Billing'])

@section('content')
<section class="card mb-8">
    <div class="app-section-head">
        <h3 class="heading-sm">اشتراكك الحالي</h3>
    </div>
    <div class="app-list">
        <div class="app-list-item">
            <div>
                <strong>الباقة النشطة</strong>
                <small>ما يحدد صلاحياتك في المنصة</small>
            </div>
            <span class="app-badge">{{ $subscription?->plan?->name_ar ?? '—' }} @if ($currentPlanCode)({{ $currentPlanCode }})@endif</span>
        </div>
        <div class="app-list-item">
            <div>
                <strong>حالة الاشتراك</strong>
                <small>{{ $subscription?->status ?? '—' }}</small>
            </div>
            @if ($subscription?->status === 'pending_payment')
                <span class="app-badge">بانتظار إتمام PayPal</span>
            @elseif ($subscription?->paypal_subscription_id && ($subscription?->plan_id ?? 0) !== ($freePlanId ?? 0))
                <span class="app-badge">PayPal مربوط</span>
            @else
                <span class="app-badge">بدون PayPal</span>
            @endif
        </div>
        @if ($subscription?->billing_cycle)
            <div class="app-list-item">
                <div><strong>دورة الفوترة</strong></div>
                <span class="app-badge">{{ $subscription->billing_cycle === 'annual' ? 'سنوي' : 'شهري' }}</span>
            </div>
        @endif
    </div>

    @if (! $paypalReady)
        <p class="text-caption mt-4">لم يُكتمل ضبط PayPal (مفاتيح ناقصة أو <code>PAYPAL_ENABLED=false</code>). يمكنك اختيار الباقة أدناه والضغط على «متابعة الدفع» لرؤية رسالة التحقق، أو طلب <strong>ترقية يدوية</strong> من الإدارة.</p>
    @endif

    @if ($isOwner && $subscription && filled($subscription->paypal_subscription_id) && (int) $subscription->plan_id !== (int) ($freePlanId ?? 0))
        <form method="POST" action="{{ route('billing.paypal.cancel') }}" class="mt-4" onsubmit="return confirm('إلغاء الاشتراك في PayPal وإرجاع الخطة المجانية؟');">
            @csrf
            <label class="app-field">
                <span>سبب الإلغاء (اختياري)</span>
                <input class="app-input" type="text" name="reason" placeholder="مثلاً: لم أعد أحتاج الميزات المدفوعة">
            </label>
            <button type="submit" class="btn btn-danger btn-sm mt-2">إلغاء الاشتراك عبر PayPal</button>
        </form>
    @endif

    @if (! $isOwner)
        <p class="text-caption mt-4">تغيير الباقة والدفع متاح لـ <strong>مالك الحساب</strong> فقط. يمكن للإدارة ترقية باقة حسابك من لوحة الحساب.</p>
    @endif
</section>

<section class="card mb-8 billing-plan-section">
    <div class="app-section-head">
        <h3 class="heading-sm">اختر الباقة التي تريد الانتقال إليها</h3>
    </div>
    <p class="text-caption mb-4">حدّد باقة واحدة ودورة الفوترة، ثم اضغط «متابعة الدفع». الباقة المجانية لا تظهر هنا — للبقاء عليها لا حاجة لإجراء.</p>

    @if ($isOwner && $paidPlans->isNotEmpty())
        @php
            $selectedPlanCode = old('plan_code');
            if ($selectedPlanCode === null || $selectedPlanCode === '') {
                $onPaidList = $paidPlans->firstWhere('code', $currentPlanCode);
                $selectedPlanCode = $onPaidList ? $currentPlanCode : $paidPlans->first()?->code;
            }
        @endphp
        <form method="POST" action="{{ route('billing.subscribe') }}" class="billing-plan-form" id="billing-plan-form" data-billing-form>
            @csrf

            <fieldset class="billing-plan-fieldset">
                <legend class="billing-plan-legend">الباقة</legend>
                <div class="billing-plan-cards" role="radiogroup" aria-label="اختيار الباقة">
                    @foreach ($paidPlans as $plan)
                        @php
                            $annual = $plan->annual_price ?? (float) $plan->monthly_price * 10;
                            $isCurrent = $currentPlanCode === $plan->code;
                        @endphp
                        <label class="billing-plan-card {{ $isCurrent ? 'is-current' : '' }}" data-billing-plan-card>
                            <input
                                type="radio"
                                name="plan_code"
                                value="{{ $plan->code }}"
                                class="billing-plan-radio"
                                @checked($selectedPlanCode === $plan->code)
                                required
                            >
                            <span class="billing-plan-card-body">
                                <span class="billing-plan-card-title">{{ $plan->name_ar }}</span>
                                <span class="billing-plan-card-code">{{ $plan->code }}</span>
                                <span class="billing-plan-card-prices">${{ number_format((float) $plan->monthly_price, 2) }}/شهر · ${{ number_format((float) $annual, 2) }}/سنة (عرض)</span>
                                @if ($isCurrent)
                                    <span class="billing-plan-card-badge">باقتك الحالية</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="app-field billing-cycle-field">
                <span>دورة الفوترة عبر PayPal</span>
                <select class="app-input" name="billing_cycle" id="billing_cycle">
                    <option value="monthly" @selected(old('billing_cycle') === 'monthly')>شهري</option>
                    <option value="annual" @selected(old('billing_cycle') === 'annual')>سنوي</option>
                </select>
            </label>

            <div class="billing-plan-fallback">
                <span class="text-caption">أو اختر من القائمة:</span>
                <select class="app-input" id="billing_plan_code_select" aria-label="اختيار الباقة من القائمة">
                    @foreach ($paidPlans as $plan)
                        <option value="{{ $plan->code }}" @selected($selectedPlanCode === $plan->code)>{{ $plan->name_ar }} ({{ $plan->code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="billing-form-actions">
                <button
                    type="submit"
                    class="btn btn-primary btn-lg"
                    @disabled($subscription?->status === 'pending_payment')
                >
                    @if ($paypalReady)
                        متابعة إلى PayPal للدفع
                    @else
                        متابعة الدفع (تحقق من إعدادات PayPal)
                    @endif
                </button>
                @if ($subscription?->status === 'pending_payment')
                    <p class="text-caption" style="margin-top:0.75rem;">لديك طلب دفع قيد الانتظار. أكمل الموافقة في نافذة PayPal إن كانت مفتوحة، أو انتظر قليلاً. للبدء من جديد يمكن للإدارة تصحيح حالة الاشتراك.</p>
                @elseif (! $paypalReady)
                    <p class="text-caption" style="margin-top:0.75rem;">أضف <code>PAYPAL_CLIENT_ID</code> و <code>PAYPAL_CLIENT_SECRET</code> في <code>.env</code> (واختياريًا <code>PAYPAL_ENABLED=false</code> لتعطيل صريح). يمكنك الضغط على الزر للتحقق — ستظهر رسالة توضيحية إن كان الضبط ناقصًا.</p>
                @endif
            </div>
        </form>
    @elseif (! $isOwner)
        <p class="app-empty">عرض الباقات متاح لمالك الحساب.</p>
    @else
        <p class="app-empty">لا توجد باقات مدفوعة مفعّلة حالياً.</p>
    @endif
</section>

<section class="mb-4">
    <h3 class="heading-sm mb-2">مقارنة سريعة</h3>
    <p class="text-caption">الأسعار بالدولار USD. الصلاحيات الفعلية مرتبطة بجدول entitlements لكل باقة في لوحة الإدارة.</p>
</section>

<div class="app-card-grid">
    @foreach ($paidPlans as $plan)
        @php
            $annual = $plan->annual_price ?? (float) $plan->monthly_price * 10;
        @endphp
        <article class="card {{ $currentPlanCode === $plan->code ? 'billing-grid-card-current' : '' }}">
            <h4 class="heading-sm mb-2">{{ $plan->name_ar }}</h4>
            <p class="text-caption mb-4">{{ $plan->name_en ?? $plan->code }}</p>
            <ul class="text-body mb-4" style="padding-inline-start: 1.1rem; line-height: 1.6; font-size: 0.9rem;">
                <li>شهري: <strong>${{ number_format((float) $plan->monthly_price, 2) }}</strong></li>
                <li>سنوي (عرض): <strong>${{ number_format((float) $annual, 2) }}</strong></li>
            </ul>
            @if ($currentPlanCode === $plan->code)
                <span class="app-badge">باقتك الحالية</span>
            @endif
        </article>
    @endforeach
</div>

<p class="text-caption mt-8">
    معرفات خطط PayPal (مثل <code>P-xxxxx</code>) تُضبط في <strong>الإدارة → الخطط</strong> أو في <code>.env</code> (<code>PAYPAL_PLAN_STARTER_*</code>، <code>PRO</code>، <code>TEAM</code>، <code>AGENCY</code>؛ وباقة <code>agency</code> تقبل أيضاً <code>PAYPAL_PLAN_ENT_*</code>). إن تركت <code>TEAM</code> أو <code>STARTER</code> فارغة يُستخدم تلقائياً ربط <code>PRO</code> إن وُجد. لاستخراج المعرفات من حسابك: <code>php artisan paypal:list-plans</code>. ترقية يدوية: <strong>الإدارة → الحسابات → الحساب → تطبيق الخطة على الحساب</strong>.
</p>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('billing-plan-form');
    if (!form) return;
    var radios = form.querySelectorAll('input[name="plan_code"][type="radio"]');
    var select = document.getElementById('billing_plan_code_select');
    if (!radios.length || !select) return;

    function syncSelectFromRadio() {
        var c = form.querySelector('input[name="plan_code"]:checked');
        if (c) select.value = c.value;
    }
    function syncRadioFromSelect() {
        var v = select.value;
        radios.forEach(function (r) { r.checked = r.value === v; });
    }
    radios.forEach(function (r) {
        r.addEventListener('change', syncSelectFromRadio);
    });
    select.addEventListener('change', syncRadioFromSelect);
    syncSelectFromRadio();

    form.addEventListener('submit', function () {
        var c = form.querySelector('input[name="plan_code"]:checked');
        if (c) select.removeAttribute('name');
    });
})();
</script>
@endpush
