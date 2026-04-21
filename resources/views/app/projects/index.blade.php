@extends('layouts.app', ['title' => 'المشاريع', 'pageTitle' => 'إدارة المشاريع', 'pageKicker' => 'Projects'])

@section('content')
<section class="app-hero mb-8">
    <div>
        <h2 class="heading-lg mb-4">كل مشاريع <span class="text-gradient">المساحة الحالية</span></h2>
        <p class="text-body-lg">هنا ترى التقدم الفعلي لكل مشروع، العميل المرتبط به، والمرحلة التي وصل إليها داخل الرحلة التسويقية.</p>
    </div>
    <div class="app-hero-actions">
        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-lg">إضافة مشروع</a>
    </div>
</section>

<section class="card mb-6">
    <form method="GET" class="app-form-grid cols-2">
        <label class="app-field">
            <span>الحالة</span>
            <select class="app-input" name="status">
                <option value="">كل الحالات</option>
                @foreach (['active', 'paused', 'completed', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label class="app-field">
            <span>المرحلة</span>
            <select class="app-input" name="stage">
                <option value="">كل المراحل</option>
                @foreach ([1, 2, 3, 4, 5] as $stage)
                    <option value="{{ $stage }}" @selected((int) request('stage') === $stage)>المرحلة {{ $stage }}</option>
                @endforeach
            </select>
        </label>
        <div class="app-form-actions cols-span-2">
            <button type="submit" class="btn btn-secondary btn-lg">تطبيق الفلترة</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="app-list">
        @forelse ($projects as $project)
            <div class="app-list-item">
                <div>
                    <strong>{{ $project->name }}</strong>
                    <small>Stage {{ $project->stage }} · {{ $project->status }} · {{ $project->client?->name ?? 'بدون عميل' }}</small>
                </div>
                <div class="app-inline-actions">
                    <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary btn-sm">عرض</a>
                    <a href="{{ route('projects.edit', $project) }}" class="btn btn-ghost btn-sm">تعديل</a>
                    <form method="POST" action="{{ route('projects.destroy', $project) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="app-empty">لا توجد مشاريع بعد داخل هذه المساحة.</p>
        @endforelse
    </div>

    <div class="admin-pagination mt-4">
        {{ $projects->links() }}
    </div>
</section>
@endsection
