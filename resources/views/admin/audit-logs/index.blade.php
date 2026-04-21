@extends('layouts.admin', ['title' => 'سجل التدقيق', 'pageTitle' => 'سجل التدقيق', 'pageKicker' => 'Audit'])

@section('content')
<section class="admin-panel mb-6">
    <form method="GET" class="admin-toolbar">
        <select name="actor_user_id" class="admin-input">
            <option value="">كل المنفذين</option>
            @foreach ($actors as $actor)
                <option value="{{ $actor->id }}" @selected((int) request('actor_user_id') === $actor->id)>{{ $actor->email }}</option>
            @endforeach
        </select>
        <input class="admin-input" name="action" value="{{ request('action') }}" placeholder="الإجراء">
        <input class="admin-input" name="target_type" value="{{ request('target_type') }}" placeholder="نوع الهدف">
        <select name="workspace_id" class="admin-input">
            <option value="">كل الـ Workspaces</option>
            @foreach ($workspaces as $workspace)
                <option value="{{ $workspace->id }}" @selected((int) request('workspace_id') === $workspace->id)>{{ $workspace->name }}</option>
            @endforeach
        </select>
        <input class="admin-input" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="admin-input" type="date" name="date_to" value="{{ request('date_to') }}">
        <button class="btn btn-secondary btn-lg" type="submit">تصفية</button>
        <a href="{{ route('admin.audit-logs.export', request()->query()) }}" class="btn btn-ghost btn-lg">CSV</a>
    </form>
</section>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>المنفذ</th>
                    <th>الإجراء</th>
                    <th>الهدف</th>
                    <th>Workspace</th>
                    <th>Meta</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($auditLogs as $log)
                    <tr>
                        <td>{{ $log->created_at?->toDateTimeString() }}</td>
                        <td>{{ $log->actor?->email ?? 'نظام' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->target_type }}#{{ $log->target_id }}</td>
                        <td>{{ $log->workspace?->name ?? '—' }}</td>
                        <td><code>{{ json_encode($log->meta, JSON_UNESCAPED_UNICODE) }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا توجد سجلات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-pagination">
        {{ $auditLogs->links() }}
    </div>
</section>
@endsection
