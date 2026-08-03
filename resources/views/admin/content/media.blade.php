@extends('layouts.app')
@section('layout', 'index')
@section('title', 'مكتبة الوسائط')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المكتبة</p>
            <h1>مكتبة الوسائط</h1>
            <p class="muted">الصور والملفات المرفوعة من محرر المحتوى، محفوظة محليًا داخل المنصة.</p>
        </div>
        <a href="{{ route('admin.content.create') }}" class="btn btn--primary">فتح المحرر</a>
    </header>

    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="GET" class="form filter-bar filter-bar--search" data-filter-bar>
        <label class="filter-bar__field filter-bar__field--search">
            <span class="filter-bar__label">البحث باسم الملف</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="اسم الصورة أو الملف">
        </label>
        <button type="submit" class="btn btn--primary">بحث</button>
    </form>

    <div class="content-media-grid">
        @forelse ($media as $item)
            <article class="card">
                @if (str_starts_with($item->mime_type, 'image/'))
                    <img src="{{ $item->url() }}" alt="{{ $item->alt_text ?: $item->original_name }}" loading="lazy" style="width:100%;aspect-ratio:16/9;object-fit:cover">
                @endif
                <div class="card__body">
                    <h2>{{ $item->original_name }}</h2>
                    <p class="muted">{{ $item->mime_type }} · {{ $item->humanReadableSize() }}</p>
                    <input class="form-control" value="{{ $item->url() }}" readonly dir="ltr" onclick="this.select()" aria-label="رابط الملف">
                    <form method="POST" action="{{ route('admin.content-media.destroy', $item) }}" data-confirm="هل تريد حذف هذا الملف نهائيًا؟">
                        @csrf @method('DELETE')
                        <button class="btn btn--ghost btn--sm">حذف الملف</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="empty-state">لا توجد وسائط مرفوعة بعد.</p>
        @endforelse
    </div>

    {{ $media->links() }}
@endsection
