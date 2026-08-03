@extends('layouts.app')
@section('layout', 'index')
@section('title', 'أقسام المكتبة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المكتبة · التنظيم</p>
            <h1>أقسام المكتبة</h1>
            <p class="muted">أنشئ تبويبات موضوعية مستقلة، ثم اربط بها المقالات والدروس والمحاضرات والدورات.</p>
        </div>
        <div class="table__actions">
            <a href="{{ route('admin.content.index') }}" class="btn btn--ghost">المحتوى</a>
            <a href="{{ route('admin.content-categories.create') }}" class="btn btn--primary">قسم جديد</a>
        </div>
    </header>

    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert--error">{{ session('error') }}</div> @endif

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>القسم</th><th>الحالة</th><th>الترتيب</th><th>المواد</th><th></th></tr></thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            <strong><span style="color: {{ $category->color }}">●</span> {{ $category->name }}</strong>
                            <p class="muted">/{{ $category->slug }} @if($category->description) · {{ $category->description }} @endif</p>
                        </td>
                        <td><span class="badge">{{ $category->is_active ? 'ظاهر' : 'مخفي' }}</span></td>
                        <td>{{ $category->sort_order }}</td>
                        <td>{{ $category->contents_count }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.content-categories.edit', $category) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.content-categories.destroy', $category) }}" data-confirm="حذف هذا القسم؟">
                                @csrf @method('DELETE')
                                <button class="btn btn--ghost btn--sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد أقسام بعد. أضف أول قسم لتنظيم المكتبة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $categories->links() }}
@endsection
