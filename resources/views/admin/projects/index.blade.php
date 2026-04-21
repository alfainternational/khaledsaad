@extends('layouts.admin', ['title' => 'المشاريع', 'pageTitle' => 'المشاريع', 'pageKicker' => 'إدارة المنصة'])

@section('content')
<section class="admin-toolbar">
    <form method="GET" class="admin-filters">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم…" class="admin-input">
        <select name="stage" class="admin-input" onchange="this.form.submit()">
            <option value="">كل المراحل</option>
            @for ($i = 1; $i <= 5; $i++)
                <option value="{{ $i }}" @selected(request('stage') == $i)>المرحلة {{ $i }}</option>
            @endfor
        </select>
        <select name="status" class="admin-input" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            @foreach (['active', 'paused', 'completed', 'archived'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary">بحث</button>
    </form>
</section>

<section class="admin-panel panel-modern">
    <div class="admin-panel-head">
        <h2>المشاريع <small>({{ $projects->total() }})</small></h2>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>مساحة العمل</th>
                    <th>العميل</th>
                    <th>المرحلة</th>
                    <th>أدوات</th>
                    <th>الحالة</th>
                    <th>الإنشاء</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td>{{ $project->id }}</td>
                        <td><a href="{{ route('admin.projects.show', $project) }}">{{ $project->name }}</a></td>
                        <td>{{ $project->workspace?->name ?? '—' }}</td>
                        <td>{{ $project->client?->name ?? '—' }}</td>
                        <td><span class="app-badge">{{ $project->stage }}</span></td>
                        <td>{{ $project->tool_runs_count }}</td>
                        <td><span class="app-badge app-badge-{{ $project->status === 'active' ? 'success' : 'muted' }}">{{ $project->status }}</span></td>
                        <td>{{ $project->created_at?->format('Y-m-d') }}</td>
                        <td>
                            <a href="{{ route('admin.projects.show', $project) }}" class="btn btn-sm btn-secondary">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="admin-empty">لا توجد مشاريع.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $projects->links() }}</div>
</section>
@endsection
