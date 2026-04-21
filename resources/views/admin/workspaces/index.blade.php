@extends('layouts.admin', ['title' => 'مساحات العمل', 'pageTitle' => 'إدارة مساحات العمل', 'pageKicker' => 'Workspaces'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>مساحات العمل</h2>
        <a href="{{ route('admin.workspaces.create') }}" class="btn btn-primary btn-lg">مساحة جديدة</a>
    </div>

    <form method="GET" class="admin-toolbar">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث باسم المساحة" class="admin-input">
        <select name="type" class="admin-input">
            <option value="">كل الأنواع</option>
            @foreach (['personal', 'team', 'agency'] as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
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
                    <th>الاسم</th>
                    <th>النوع</th>
                    <th>الخطة</th>
                    <th>الحالة</th>
                    <th>الإعدادات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workspaces as $workspace)
                    <tr>
                        <td>
                            <strong>{{ $workspace->name }}</strong>
                            <small>أعضاء: {{ $workspace->members_count }} | مشاريع: {{ $workspace->projects_count }} | عملاء: {{ $workspace->clients_count }}</small>
                        </td>
                        <td>{{ $workspace->type }}</td>
                        <td>{{ $workspace->account?->subscription?->plan?->name_ar ?? 'بدون خطة' }}</td>
                        <td>{{ $workspace->status }}</td>
                        <td>
                            <div class="admin-actions-cell">
                                <a href="{{ route('admin.workspaces.show', $workspace) }}" class="btn btn-secondary btn-sm">التفاصيل</a>
                                <a href="{{ route('admin.workspaces.edit', $workspace) }}" class="btn btn-ghost btn-sm">تعديل</a>
                                <a href="{{ route('admin.workspaces.entitlements.index', $workspace) }}" class="btn btn-ghost btn-sm">Entitlements</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد مساحات عمل بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-pagination">
        {{ $workspaces->links() }}
    </div>
</section>
@endsection
