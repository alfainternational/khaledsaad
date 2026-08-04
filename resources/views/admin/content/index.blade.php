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
        <div class="page-head__actions">
            <a href="{{ route('admin.content-categories.index') }}" class="btn btn--ghost">إدارة الأقسام</a>
            <a href="{{ route('admin.content.create') }}" class="btn btn--primary">محتوى جديد</a>
        </div>
    </header>

    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="GET" class="form filter-bar" data-filter-bar>
        <label class="filter-bar__field filter-bar__field--search">
            <span class="filter-bar__label">البحث</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="العنوان أو الملخص">
        </label>
        <label class="filter-bar__field">
            <span class="filter-bar__label">النوع</span>
            <select name="type">
                <option value="">كل الأنواع</option>
                @foreach (['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="filter-bar__field">
            <span class="filter-bar__label">القسم</span>
            <select name="category_id">
                <option value="">كل الأقسام</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="filter-bar__field">
            <span class="filter-bar__label">الحالة</span>
            <select name="status">
                <option value="">كل الحالات</option>
                @foreach (['draft' => 'مسودة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="filter-bar__field">
            <span class="filter-bar__label">الوصول</span>
            <select name="access_level">
                <option value="">كل مستويات الوصول</option>
                <option value="public" @selected(request('access_level') === 'public')>مجاني</option>
                <option value="subscribers" @selected(request('access_level') === 'subscribers')>بعد تسجيل البريد</option>
            </select>
        </label>
        <button type="submit" class="btn btn--primary">تطبيق</button>
    </form>

    <div class="table-wrap">
        <table class="table" data-table="entity">
            <thead><tr><th>المحتوى</th><th>النوع</th><th>القسم</th><th>الحالة</th><th>الوصول</th><th>آخر تحديث</th><th></th></tr></thead>
            <tbody>
                @forelse ($contents as $item)
                    <tr>
                        <td class="table__cell--primary" data-label="المحتوى"><strong>{{ $item->title }}</strong><p class="muted">/{{ $item->slug }}</p></td>
                        <td data-label="النوع">{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$item->type] ?? $item->type }}</td>
                        <td data-label="القسم">{{ $item->category?->name ?: 'غير مصنف' }}</td>
                        <td data-label="الحالة"><span class="badge">{{ ['draft' => 'مسودة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف'][$item->status] ?? $item->status }}</span></td>
                        <td data-label="الوصول">{{ $item->isSubscriberOnly() ? 'بعد تسجيل البريد' : 'مجاني' }}</td>
                        <td data-label="آخر تحديث">{{ $item->updated_at?->diffForHumans() }}</td>
                        <td class="table__actions" data-label="الإجراءات">
                            <a href="{{ route('admin.content.edit', $item) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            @if ($item->status === 'archived')
                                <form method="POST" action="{{ route('admin.content.restore', $item) }}">@csrf @method('PATCH')<button class="btn btn--ghost btn--sm">استعادة</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.content.archive', $item) }}">@csrf @method('PATCH')<button class="btn btn--ghost btn--sm">أرشفة</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" data-label="">لا يوجد محتوى بعد. أنشئ أول عنصر من هنا.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $contents->links() }}
@endsection
