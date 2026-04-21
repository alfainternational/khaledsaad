@extends('layouts.admin', ['title' => 'الحسابات', 'pageTitle' => 'إدارة الحسابات', 'pageKicker' => 'Accounts'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>الحسابات</h2>
        <a href="{{ route('admin.accounts.create') }}" class="btn btn-primary btn-lg">حساب جديد</a>
    </div>

    <form method="GET" class="admin-toolbar">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث باسم الحساب أو البريد" class="admin-input">
        <select name="status" class="admin-input">
            <option value="">كل الحالات</option>
            @foreach (['active', 'suspended', 'archived'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary btn-lg" type="submit">تطبيق</button>
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
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td>
                            <strong>{{ $account->name }}</strong>
                            <small>{{ $account->billing_email }}</small>
                        </td>
                        <td>{{ $account->owner?->email }}</td>
                        <td>{{ $account->subscription?->plan?->name_ar ?? 'بدون خطة' }}</td>
                        <td>{{ $account->status }}</td>
                        <td>
                            <div class="admin-actions-cell">
                                <a href="{{ route('admin.accounts.show', $account) }}" class="btn btn-secondary btn-sm">عرض</a>
                                <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn-ghost btn-sm">تعديل</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد حسابات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-pagination">
        {{ $accounts->links() }}
    </div>
</section>
@endsection
