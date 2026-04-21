@extends('layouts.admin', ['title' => 'العملاء', 'pageTitle' => 'العملاء', 'pageKicker' => 'إدارة المنصة'])

@section('content')
<section class="admin-toolbar">
    <form method="GET" class="admin-filters">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم…" class="admin-input">
        <select name="status" class="admin-input" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            @foreach (['active', 'archived'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary">بحث</button>
    </form>
</section>

<section class="admin-panel panel-modern">
    <div class="admin-panel-head">
        <h2>العملاء <small>({{ $clients->total() }})</small></h2>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>مساحة العمل</th>
                    <th>المشاريع</th>
                    <th>الحالة</th>
                    <th>الإنشاء</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    <tr>
                        <td>{{ $client->id }}</td>
                        <td><a href="{{ route('admin.clients.show', $client) }}">{{ $client->name }}</a></td>
                        <td>{{ $client->workspace?->name ?? '—' }}</td>
                        <td>{{ $client->projects_count }}</td>
                        <td><span class="app-badge app-badge-{{ $client->status === 'active' ? 'success' : 'muted' }}">{{ $client->status }}</span></td>
                        <td>{{ $client->created_at?->format('Y-m-d') }}</td>
                        <td>
                            <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-sm btn-secondary">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="admin-empty">لا يوجد عملاء.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $clients->links() }}</div>
</section>
@endsection
