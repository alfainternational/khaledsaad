@extends('layouts.admin', ['title' => 'مخرجات AI', 'pageTitle' => 'سجل مخرجات الذكاء الاصطناعي', 'pageKicker' => 'المراقبة'])

@section('content')
<section class="admin-toolbar">
    <form method="GET" class="admin-filters">
        <select name="status" class="admin-input" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            @foreach (['completed', 'needs_input', 'failed', 'pending'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="template_id" class="admin-input" onchange="this.form.submit()">
            <option value="">كل القوالب</option>
            @foreach ($templates as $tpl)
                <option value="{{ $tpl->id }}" @selected((string) request('template_id') === (string) $tpl->id)>{{ $tpl->name }}</option>
            @endforeach
        </select>
        <select name="ops_review_status" class="admin-input" onchange="this.form.submit()">
            <option value="">كل وسوم التشغيل</option>
            @foreach (['open', 'investigating', 'resolved', 'voided'] as $ops)
                <option value="{{ $ops }}" @selected(request('ops_review_status') === $ops)>{{ $ops }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="admin-input">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="admin-input">
        <button type="submit" class="btn btn-secondary">تصفية</button>
    </form>
</section>

<section class="admin-panel panel-modern">
    <div class="admin-panel-head">
        <h2>مخرجات AI <small>({{ $generations->total() }})</small></h2>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>القالب</th>
                    <th>الحساب</th>
                    <th>المستخدم</th>
                    <th>التوكنات</th>
                    <th>الحالة</th>
                    <th>التشغيل</th>
                    <th>التاريخ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($generations as $gen)
                    <tr>
                        <td>{{ $gen->id }}</td>
                        <td>{{ $gen->template?->name ?? '—' }}</td>
                        <td>{{ $gen->account?->name ?? '—' }}</td>
                        <td>{{ $gen->author?->name ?? '—' }}</td>
                        <td>{{ number_format($gen->tokens_used ?? 0) }}</td>
                        <td><span class="app-badge app-badge-{{ $gen->status === 'completed' ? 'success' : ($gen->status === 'failed' ? 'danger' : 'muted') }}">{{ $gen->status }}</span></td>
                        <td>
                            @if ($gen->ops_review_status)
                                <span class="app-badge app-badge-muted">{{ $gen->ops_review_status }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $gen->created_at?->format('Y-m-d H:i') }}</td>
                        <td><a href="{{ route('admin.ai-generations.show', $gen) }}" class="btn btn-sm btn-secondary">عرض</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="admin-empty">لا توجد سجلات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $generations->links() }}</div>
</section>
@endsection
