@extends('layouts.admin', ['title' => 'المستخدمون', 'pageTitle' => 'إدارة المستخدمين', 'pageKicker' => 'Users'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>المستخدمون</h2>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-lg">مستخدم جديد</a>
    </div>

    <form method="GET" class="admin-toolbar">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث بالاسم أو البريد" class="admin-input">
        <select name="status" class="admin-input">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>
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
                    <th>المستخدم</th>
                    <th>الحالة</th>
                    <th>مدير عام</th>
                    <th>آخر دخول</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            <small>{{ $user->email }}</small>
                        </td>
                        <td>{{ $user->status->value }}</td>
                        <td>{{ $user->is_super_admin ? 'نعم' : 'لا' }}</td>
                        <td>{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary btn-sm">عرض</a>
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">تعديل</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">لا يوجد مستخدمون بعد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-pagination">
        {{ $users->links() }}
    </div>
</section>
@endsection
