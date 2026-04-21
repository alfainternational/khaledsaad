@extends('layouts.admin', ['title' => 'الاشتراكات', 'pageTitle' => 'مراقبة الاشتراكات', 'pageKicker' => 'Subscriptions'])

@section('content')
<section class="admin-toolbar">
    <form method="GET" class="admin-filters">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="اسم الحساب، البريد…" class="admin-input">
        <select name="status" class="admin-input" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            @foreach ($subscriptionStatuses as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
            @endforeach
        </select>
        <select name="plan_id" class="admin-input" onchange="this.form.submit()">
            <option value="">كل الخطط</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name_ar }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary">تصفية</button>
    </form>
</section>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>الحساب</th>
                    <th>المالك</th>
                    <th>الخطة</th>
                    <th>الحالة</th>
                    <th>تنتهي في</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->account?->name }}</td>
                        <td>{{ $subscription->account?->owner?->email }}</td>
                        <td>{{ $subscription->plan?->name_ar }}</td>
                        <td><span class="app-badge">{{ $subscription->status }}</span></td>
                        <td>{{ $subscription->current_period_end?->toDateString() ?? '—' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="btn btn-primary btn-sm">تشغيل الاشتراك</a>
                            <a href="{{ route('admin.accounts.show', $subscription->account) }}" class="btn btn-secondary btn-sm">الحساب</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا توجد اشتراكات مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $subscriptions->links() }}</div>
</section>
@endsection
