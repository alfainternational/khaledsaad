@extends('layouts.admin', ['title' => $client->name, 'pageTitle' => $client->name, 'pageKicker' => 'تفاصيل العميل'])

@section('content')
<section class="admin-detail-header mb-6">
    <div>
        <h2>{{ $client->name }}</h2>
        <p>{{ $client->workspace?->name ?? '—' }} · {{ $client->status }}</p>
    </div>
    <div class="admin-detail-actions">
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-secondary">تعديل</a>
        <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" onsubmit="return confirm('هل أنت متأكد؟')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">حذف</button>
        </form>
    </div>
</section>

<section class="admin-grid admin-two-col mb-8">
    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>البيانات الأساسية</h2></div>
        <div class="admin-list">
            <div class="admin-list-item"><div><strong>المعرف</strong></div><span>{{ $client->public_id }}</span></div>
            <div class="admin-list-item"><div><strong>مساحة العمل</strong></div><span>{{ $client->workspace?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>الحساب</strong></div><span>{{ $client->workspace?->account?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>المالك</strong></div><span>{{ $client->workspace?->account?->owner?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>معلومات التواصل</strong></div><span>{{ $client->contact_info ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>الحالة</strong></div><span class="app-badge">{{ $client->status }}</span></div>
            <div class="admin-list-item"><div><strong>تاريخ الإنشاء</strong></div><span>{{ $client->created_at?->format('Y-m-d H:i') }}</span></div>
        </div>
    </article>

    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>المشاريع ({{ $client->projects->count() }})</h2></div>
        <div class="admin-list">
            @forelse ($client->projects as $project)
                <a href="{{ route('admin.projects.show', $project) }}" class="admin-list-item">
                    <div>
                        <strong>{{ $project->name }}</strong>
                        <small>المرحلة {{ $project->stage }} · {{ $project->tool_runs_count }} أداة</small>
                    </div>
                    <span class="app-badge">{{ $project->status }}</span>
                </a>
            @empty
                <p class="admin-empty">لا توجد مشاريع لهذا العميل.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection
