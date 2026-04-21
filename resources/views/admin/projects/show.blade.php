@extends('layouts.admin', ['title' => $project->name, 'pageTitle' => $project->name, 'pageKicker' => 'تفاصيل المشروع'])

@section('content')
<section class="admin-detail-header mb-6">
    <div>
        <h2>{{ $project->name }}</h2>
        <p>المرحلة {{ $project->stage }} · {{ $project->status }} · {{ $project->workspace?->name ?? '—' }}</p>
    </div>
    <div class="admin-detail-actions">
        <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-secondary">تعديل</a>
        <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('هل أنت متأكد؟')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">حذف</button>
        </form>
    </div>
</section>

<section class="admin-grid admin-two-col mb-8">
    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>البيانات الأساسية</h2></div>
        <div class="admin-list">
            <div class="admin-list-item"><div><strong>المعرف</strong></div><span>{{ $project->public_id }}</span></div>
            <div class="admin-list-item"><div><strong>مساحة العمل</strong></div><span>{{ $project->workspace?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>الحساب</strong></div><span>{{ $project->workspace?->account?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>العميل</strong></div><span>{{ $project->client?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>المرحلة</strong></div><span class="app-badge">{{ $project->stage }}</span></div>
            <div class="admin-list-item"><div><strong>الحالة</strong></div><span class="app-badge">{{ $project->status }}</span></div>
            <div class="admin-list-item"><div><strong>تاريخ الإنشاء</strong></div><span>{{ $project->created_at?->format('Y-m-d H:i') }}</span></div>
        </div>
    </article>

    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>آخر استخدامات الأدوات</h2></div>
        <div class="admin-list">
            @forelse ($project->toolRuns as $run)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $run->tool?->name ?? $run->tool_code }}</strong>
                        <small>{{ $run->mode }} · {{ $run->completeness_score ?? 0 }}%</small>
                    </div>
                    <span>{{ $run->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="admin-empty">لا توجد استخدامات بعد.</p>
            @endforelse
        </div>
    </article>
</section>

@if ($project->approvals->isNotEmpty())
<section class="admin-panel panel-modern mb-8">
    <div class="admin-panel-head"><h2>الموافقات</h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>النوع</th><th>الحالة</th><th>المراجع</th><th>ملاحظة</th><th>التاريخ</th></tr></thead>
            <tbody>
                @foreach ($project->approvals as $approval)
                    <tr>
                        <td>{{ $approval->item_type }}</td>
                        <td><span class="app-badge">{{ $approval->status }}</span></td>
                        <td>{{ $approval->reviewer?->name ?? '—' }}</td>
                        <td>{{ $approval->note ?? '—' }}</td>
                        <td>{{ $approval->created_at?->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
