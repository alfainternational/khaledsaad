@extends('layouts.admin', ['title' => 'سجل الأدوات', 'pageTitle' => 'سجل استخدام الأدوات', 'pageKicker' => 'المراقبة'])

@section('content')
<section class="admin-toolbar">
    <form method="GET" class="admin-filters">
        <select name="tool_code" class="admin-input" onchange="this.form.submit()">
            <option value="">كل الأدوات</option>
            @foreach ($toolCodes as $code)
                <option value="{{ $code }}" @selected(request('tool_code') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        <input type="number" name="workspace_id" value="{{ request('workspace_id') }}" class="admin-input" placeholder="معرّف مساحة العمل" min="1">
        <select name="ops_review_status" class="admin-input" onchange="this.form.submit()">
            <option value="">كل حالات التشغيل</option>
            @foreach (['open', 'investigating', 'resolved', 'voided'] as $ops)
                <option value="{{ $ops }}" @selected(request('ops_review_status') === $ops)>{{ $ops }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="admin-input" placeholder="من تاريخ">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="admin-input" placeholder="إلى تاريخ">
        <button type="submit" class="btn btn-secondary">تصفية</button>
    </form>
</section>

<section class="admin-panel panel-modern">
    <div class="admin-panel-head">
        <h2>سجل الأدوات <small>({{ $toolRuns->total() }})</small></h2>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الأداة</th>
                    <th>الوضع</th>
                    <th>المشروع</th>
                    <th>مساحة العمل</th>
                    <th>المستخدم</th>
                    <th>الاكتمال</th>
                    <th>التشغيل</th>
                    <th>التاريخ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($toolRuns as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td>{{ $run->tool?->name ?? $run->tool_code }}</td>
                        <td><span class="app-badge">{{ $run->mode }}</span></td>
                        <td>{{ $run->project?->name ?? '—' }}</td>
                        <td>{{ $run->workspace?->name ?? '—' }}</td>
                        <td>{{ $run->author?->name ?? '—' }}</td>
                        <td>{{ $run->completeness_score ?? 0 }}%</td>
                        <td>
                            @if ($run->ops_review_status)
                                <span class="app-badge app-badge-muted">{{ $run->ops_review_status }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $run->created_at?->format('Y-m-d H:i') }}</td>
                        <td><a href="{{ route('admin.tool-runs.show', $run) }}" class="btn btn-sm btn-secondary">عرض</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="admin-empty">لا توجد سجلات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $toolRuns->links() }}</div>
</section>
@endsection
