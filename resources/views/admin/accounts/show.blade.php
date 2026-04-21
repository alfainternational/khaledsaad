@extends('layouts.admin', ['title' => 'تفاصيل الحساب', 'pageTitle' => 'تفاصيل الحساب', 'pageKicker' => 'Account'])

@section('content')
<section class="admin-grid admin-two-col mb-6">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>{{ $account->name }}</h2>
        </div>
        <div class="admin-meta-list">
            <div><span>البريد المحاسبي</span><strong>{{ $account->billing_email }}</strong></div>
            <div><span>المالك</span><strong>{{ $account->owner?->email }}</strong></div>
            <div><span>الخطة الحالية</span><strong>{{ $account->subscription?->plan?->name_ar ?? 'بدون خطة' }}</strong></div>
            <div><span>الحالة</span><strong>{{ $account->status }}</strong></div>
            <div><span>عدد الـ Workspaces</span><strong>{{ $account->workspaces->count() }}</strong></div>
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>تشغيل الحساب</h2>
            <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn-secondary btn-sm">تعديل البيانات</a>
        </div>
        <div class="admin-form-stack">
            <form method="POST" action="{{ route('admin.accounts.status', $account) }}" class="admin-form-grid cols-2">
                @csrf
                @method('PATCH')
                <label class="admin-field">
                    <span>حالة الحساب</span>
                    <select name="status" class="admin-input">
                        @foreach ($accountStatuses as $status)
                            <option value="{{ $status }}" @selected($account->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="admin-form-actions admin-align-end">
                    <button type="submit" class="btn btn-secondary btn-lg">تحديث الحالة</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.accounts.subscription', $account) }}" class="admin-form-grid cols-2">
                @csrf
                @method('PATCH')
                <div class="admin-field cols-span-2">
                    <p class="text-caption" style="margin:0 0 0.75rem;line-height:1.6;">
                        <strong>ترقية أو تغيير باقة المستخدم:</strong> اختر الخطة والحالة ثم احفظ. بشكل افتراضي يتم <strong>إزالة ربط PayPal</strong> حتى تتطابق صلاحيات التطبيق مع ما اخترته (إن كان للعميل اشتراك PayPal نشط، يُفضّل إلغاؤه من لوحة PayPal لتفادي فوترة مزدوجة).
                    </p>
                    @if (filled($account->subscription?->paypal_subscription_id))
                        <p class="text-caption" style="margin:0;color:var(--warning, #d97706);">معرف PayPal الحالي: <code>{{ $account->subscription->paypal_subscription_id }}</code></p>
                    @endif
                </div>
                <label class="admin-field cols-span-2">
                    <span>اختر الخطة</span>
                    <select name="plan_id" class="admin-input" size="1">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(old('plan_id', $account->subscription?->plan_id) == $plan->id)>
                                {{ $plan->name_ar }} — {{ $plan->code }} — ${{ number_format((float) $plan->monthly_price, 2) }}/شهر
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-field">
                    <span>حالة الاشتراك</span>
                    <select name="status" class="admin-input">
                        @foreach ($subscriptionStatuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $account->subscription?->status ?? 'active') === $status)>{{ $status }}</option>
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
                <label class="admin-field cols-span-2 admin-checkbox-row">
                    <input type="hidden" name="keep_paypal_link" value="0">
                    <input type="checkbox" name="keep_paypal_link" value="1" @checked(old('keep_paypal_link', false))>
                    <span>الإبقاء على ربط PayPal الحالي (متقدم — قد يسبب عدم تطابق مع الفاتورة)</span>
                </label>
                <div class="admin-form-actions cols-span-2">
                    <button type="submit" class="btn btn-primary btn-lg">تطبيق الخطة على الحساب</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-lg">حذف الحساب نهائياً</button>
            </form>
        </div>
    </article>
</section>

<section class="admin-grid admin-two-col">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>مساحات العمل</h2>
        </div>
        <div class="admin-list">
            @forelse ($account->workspaces as $workspace)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $workspace->name }}</strong>
                        <small>{{ $workspace->type }} | أعضاء: {{ $workspace->members_count }} | مشاريع: {{ $workspace->projects_count }} | عملاء: {{ $workspace->clients_count }}</small>
                    </div>
                    <div class="admin-actions-cell">
                        <a href="{{ route('admin.workspaces.show', $workspace) }}" class="btn btn-secondary btn-sm">التفاصيل</a>
                        <a href="{{ route('admin.workspaces.entitlements.index', $workspace) }}" class="btn btn-ghost btn-sm">الـ Overrides</a>
                    </div>
                </div>
            @empty
                <p class="admin-empty">لا توجد Workspaces مرتبطة بهذا الحساب.</p>
            @endforelse
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>ملخص سريع</h2>
        </div>
        <div class="admin-list">
            <div class="admin-list-item">
                <div>
                    <strong>Public ID</strong>
                    <small>{{ $account->public_id }}</small>
                </div>
            </div>
            <div class="admin-list-item">
                <div>
                    <strong>الخطة الحالية</strong>
                    <small>{{ $account->subscription?->plan?->name_ar ?? 'بدون خطة' }}</small>
                </div>
                <span>{{ $account->subscription?->status ?? '—' }}</span>
            </div>
            <div class="admin-list-item">
                <div>
                    <strong>السعر الشهري</strong>
                    <small>الفاتورة الحالية</small>
                </div>
                <span>{{ number_format((float) ($account->subscription?->plan?->monthly_price ?? 0), 2) }}</span>
            </div>
        </div>
    </article>
</section>
@endsection
