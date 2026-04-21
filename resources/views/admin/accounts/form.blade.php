@extends('layouts.admin', ['title' => 'الحساب', 'pageTitle' => $account->exists ? 'تعديل الحساب' : 'إنشاء حساب', 'pageKicker' => 'Accounts'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>بيانات الحساب</h2>
        </div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>اسم الحساب</span>
                <input class="admin-input" name="name" value="{{ old('name', $account->name) }}">
            </label>
            <label class="admin-field">
                <span>البريد المحاسبي</span>
                <input class="admin-input" type="email" name="billing_email" value="{{ old('billing_email', $account->billing_email) }}">
            </label>
            <label class="admin-field">
                <span>مالك الحساب</span>
                <select class="admin-input" name="owner_user_id">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('owner_user_id', $account->owner_user_id) === $user->id)>{{ $user->name }} - {{ $user->email }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>الحالة</span>
                <select class="admin-input" name="status">
                    @foreach (['active', 'suspended', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $account->status ?? 'active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>الاشتراك</h2>
        </div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>الخطة</span>
                <select class="admin-input" name="plan_id">
                    <option value="">بدون اشتراك</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((int) old('plan_id', $account->subscription?->plan_id) === $plan->id)>{{ $plan->name_ar }} ({{ $plan->code }})</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>حالة الاشتراك</span>
                <select class="admin-input" name="subscription_status">
                    <option value="">—</option>
                    @foreach ($subscriptionStatuses as $status)
                        <option value="{{ $status }}" @selected(old('subscription_status', $account->subscription?->status ?? 'active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field cols-span-2">
                <span>نهاية الفترة الحالية</span>
                <input
                    type="datetime-local"
                    name="current_period_end"
                    class="admin-input"
                    value="{{ old('current_period_end', optional($account->subscription?->current_period_end)->format('Y-m-d\TH:i')) }}"
                >
            </label>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $account->exists ? 'حفظ التعديلات' : 'إنشاء الحساب' }}</button>
        <a href="{{ $account->exists ? route('admin.accounts.show', $account) : route('admin.accounts.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection
