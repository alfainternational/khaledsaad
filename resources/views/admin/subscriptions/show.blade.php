@extends('layouts.admin', ['title' => 'تشغيل الاشتراك', 'pageTitle' => 'تشغيل الاشتراك', 'pageKicker' => 'Subscriptions'])

@section('content')
<section class="admin-detail-header mb-6">
    <div>
        <h2>{{ $subscription->account?->name ?? 'حساب' }} — {{ $subscription->plan?->name_ar ?? '—' }}</h2>
        <p>{{ $subscription->status }} · ينتهي {{ $subscription->current_period_end?->toDateTimeString() ?? '—' }}</p>
    </div>
    <div class="admin-actions-cell">
        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-secondary">كل الاشتراكات</a>
        <a href="{{ route('admin.accounts.show', $subscription->account) }}" class="btn btn-secondary">صفحة الحساب</a>
    </div>
</section>

<section class="admin-grid admin-two-col mb-8">
    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>تعديل الخطة والحالة</h2></div>
        <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}" class="admin-form-stack">
            @csrf
            @method('PATCH')
            <p class="text-caption admin-field">نفس منطق «تشغيل الحساب» في صفحة الحساب، مع تسجيل في سجل التدقيق.</p>
            @if (filled($subscription->paypal_subscription_id))
                <div class="app-alert warning">
                    معرف PayPal: <code>{{ $subscription->paypal_subscription_id }}</code>
                </div>
            @endif
            <label class="admin-field">
                <span>الخطة</span>
                <select name="plan_id" class="admin-input">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('plan_id', $subscription->plan_id) == $plan->id)>
                            {{ $plan->name_ar }} — {{ $plan->code }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>حالة الاشتراك</span>
                <select name="status" class="admin-input">
                    @foreach ($subscriptionStatuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $subscription->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>نهاية الفترة الحالية</span>
                <input type="datetime-local" name="current_period_end" class="admin-input"
                    value="{{ old('current_period_end', optional($subscription->current_period_end)->format('Y-m-d\TH:i')) }}">
            </label>
            <label class="admin-field admin-checkbox-row">
                <input type="hidden" name="keep_paypal_link" value="0">
                <input type="checkbox" name="keep_paypal_link" value="1" @checked(old('keep_paypal_link', false))>
                <span>الإبقاء على ربط PayPal الحالي</span>
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary btn-lg">حفظ تعديل الاشتراك</button>
            </div>
        </form>
    </article>

    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>تمديد سريع للفترة</h2></div>
        <form method="POST" action="{{ route('admin.subscriptions.extend', $subscription) }}" class="admin-form-stack">
            @csrf
            <label class="admin-field">
                <span>عدد الأيام المضافة من اليوم أو من نهاية الفترة الحالية (أيهما أبعد)</span>
                <input type="number" name="days" class="admin-input" min="1" max="730" value="{{ old('days', 30) }}" required>
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-secondary btn-lg">تمديد الفترة</button>
            </div>
        </form>
    </article>
</section>
@endsection
