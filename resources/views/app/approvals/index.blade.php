@extends('layouts.app', ['title' => 'الاعتمادات', 'pageTitle' => 'المراجعات والاعتمادات', 'pageKicker' => 'Approvals'])

@section('content')
@php
    $statusLabels = ['pending' => 'قيد المراجعة', 'approved' => 'معتمد', 'rejected' => 'مرفوض'];
    $statusBadgeClasses = ['pending' => 'app-badge-warning', 'approved' => 'app-badge-success', 'rejected' => 'app-badge-danger'];
@endphp

<section class="app-stat-grid mb-8">
    <article class="card stat-card-modern">
        <span class="app-stat-label">قيد المراجعة</span>
        <strong class="app-stat-value">{{ $pendingCount }}</strong>
    </article>
    <article class="card stat-card-modern">
        <span class="app-stat-label">معتمد</span>
        <strong class="app-stat-value">{{ $approvedCount }}</strong>
    </article>
    <article class="card stat-card-modern">
        <span class="app-stat-label">مرفوض</span>
        <strong class="app-stat-value">{{ $rejectedCount }}</strong>
    </article>
</section>

<section class="card mb-6">
    <form method="GET" class="app-form-grid cols-2">
        <label class="app-field">
            <span>الحالة</span>
            <select class="app-input" name="status">
                <option value="">كل الحالات</option>
                @foreach (['pending', 'approved', 'rejected'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] ?? $status }}</option>
                @endforeach
            </select>
        </label>
        <div class="app-form-actions">
            <button type="submit" class="btn btn-secondary btn-lg">تطبيق الفلترة</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="app-list">
        @forelse ($approvals as $approval)
            @php
                $toolRun = $approval->item_type === 'tool_run' ? $approval->toolRun : null;
                $generation = $approval->item_type === 'ai_generation' ? $approval->aiGeneration : null;
                $executionPackage = $approval->item_type === 'execution_package' ? $approval->executionPackage : null;
                $itemTitle = $toolRun?->summary_json['headline']
                    ?? $toolRun?->tool?->name
                    ?? $generation?->headline
                    ?? $generation?->template?->name
                    ?? $executionPackage?->title
                    ?? 'عنصر يحتاج مراجعة';
                $itemKind = match ($approval->item_type) {
                    'tool_run' => 'تشغيل أداة',
                    'ai_generation' => 'مخرج استوديو',
                    'execution_package' => 'حزمة تنفيذ',
                    default => $approval->item_type,
                };
                $sourceUrl = $toolRun?->tool
                    ? route('tools.show', $toolRun->tool)
                    : ($generation
                        ? route('studio.generations.show', $generation)
                        : ($executionPackage ? route('execution-packages.show', $executionPackage) : null));
            @endphp
            <div class="app-list-item app-approval-item">
                <div>
                    <strong>{{ $itemTitle }}</strong>
                    <small>
                        {{ $itemKind }} ·
                        {{ $approval->project?->name ?? 'عنصر بدون مشروع' }} ·
                        {{ $approval->project?->client?->name ?? 'بدون عميل' }} ·
                        {{ $approval->reviewer?->name ?? 'بدون مراجع' }}
                    </small>
                    @if ($approval->note)
                        <small>{{ $approval->note }}</small>
                    @endif
                </div>
                <div class="app-inline-actions">
                    <span class="app-badge {{ $statusBadgeClasses[$approval->status] ?? '' }}">{{ $statusLabels[$approval->status] ?? $approval->status }}</span>
                    @if ($sourceUrl)
                        <a href="{{ $sourceUrl }}" class="btn btn-secondary btn-sm">فتح المصدر</a>
                    @endif
                    @if ($approval->status === 'pending')
                        <form method="POST" action="{{ route('approvals.update', $approval) }}" class="app-inline-form">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="approved">
                            <button type="submit" class="btn btn-secondary btn-sm">اعتماد</button>
                        </form>
                        <form method="POST" action="{{ route('approvals.update', $approval) }}" class="app-inline-form">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="btn btn-ghost btn-sm">رفض</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="app-empty">لا توجد طلبات اعتماد ضمن الفلاتر الحالية.</p>
        @endforelse
    </div>
    <div class="admin-pagination mt-4">
        {{ $approvals->links() }}
    </div>
</section>
@endsection
