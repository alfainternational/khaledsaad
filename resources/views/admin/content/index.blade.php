@extends('layouts.app')
@section('layout', 'index')
@section('title', 'إدارة المحتوى')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المكتبة</p>
            <h1>إدارة المحتوى</h1>
            <p class="muted">أنشئ المقالات والدروس والمحاضرات والدورات وانشرها من مكان واحد.</p>
        </div>
        <a href="{{ route('admin.content.create') }}" class="btn btn--primary">محتوى جديد</a>
    </header>

    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="GET" class="form filter-bar">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="ابحث بالعنوان أو الملخص">
        <select name="type">
            <option value="">كل الأنواع</option>
            @foreach (['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'] as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">كل الحالات</option>
            @foreach (['draft' => 'مسودة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="access_level">
            <option value="">كل مستويات الوصول</option>
            <option value="public" @selected(request('access_level') === 'public')>مجاني</option>
            <option value="subscribers" @selected(request('access_level') === 'subscribers')>بعد تسجيل البريد</option>
        </select>
        <button class="btn btn--ghost">تصفية</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>المحتوى</th><th>النوع</th><th>الحالة</th><th>الوصول</th><th>آخر تحديث</th><th></th></tr></thead>
            <tbody>
                @forelse ($contents as $item)
                    <tr>
                        <td><strong>{{ $item->title }}</strong><p class="muted">/{{ $item->slug }}</p></td>
                        <td>{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$item->type] ?? $item->type }}</td>
                        <td><span class="badge">{{ ['draft' => 'مسودة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف'][$item->status] ?? $item->status }}</span></td>
                        <td>{{ $item->isSubscriberOnly() ? 'بعد تسجيل البريد' : 'مجاني' }}</td>
                        <td>{{ $item->updated_at?->diffForHumans() }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.content.edit', $item) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            @if ($item->status === 'archived')
                                <form method="POST" action="{{ route('admin.content.restore', $item) }}">@csrf @method('PATCH')<button class="btn btn--ghost btn--sm">استعادة</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.content.archive', $item) }}">@csrf @method('PATCH')<button class="btn btn--ghost btn--sm">أرشفة</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا يوجد محتوى بعد. أنشئ أول عنصر من هنا.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $contents->links() }}
@endsection
