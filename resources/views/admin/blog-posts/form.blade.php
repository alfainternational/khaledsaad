@extends('layouts.admin', ['title' => 'مقال', 'pageTitle' => $post->exists ? 'تعديل مقال' : 'مقال جديد', 'pageKicker' => 'Blog'])

@section('content')
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- ── المحتوى الأساسي ── --}}
    <section class="admin-panel">
        <div class="admin-panel-head"><h2>المحتوى الأساسي</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>العنوان</span>
                <input class="admin-input" name="title" value="{{ old('title', $post->title) }}" required maxlength="200">
            </label>
            <label class="admin-field">
                <span>slug (لاتيني)</span>
                <input class="admin-input" name="slug" value="{{ old('slug', $post->slug) }}" required maxlength="160" dir="ltr">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>مقتطف</span>
                <textarea class="admin-input" name="excerpt" rows="3" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea>
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>المحتوى (HTML)</span>
                <textarea class="admin-input" name="body_html" rows="20" required dir="ltr">{{ old('body_html', $post->body_html) }}</textarea>
            </label>
        </div>
    </section>

    {{-- ── التصنيف والتاغز ── --}}
    <section class="admin-panel">
        <div class="admin-panel-head"><h2>التصنيف والتاغز</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>التصنيف</span>
                <input class="admin-input" name="category" value="{{ old('category', $post->category) }}" maxlength="100" placeholder="مثال: تسويق، استراتيجية">
            </label>
            <label class="admin-field">
                <span>وقت القراءة (دقائق)</span>
                <input class="admin-input" type="number" name="reading_time_minutes" value="{{ old('reading_time_minutes', $post->reading_time_minutes) }}" min="1" max="999" placeholder="يُحسب تلقائياً إذا تُرك فارغاً">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>التاغز (مفصولة بفواصل)</span>
                <input class="admin-input" name="tags"
                    value="{{ old('tags', is_array($post->tags) ? implode(', ', $post->tags) : $post->tags) }}"
                    placeholder="تسويق رقمي, SEO, محتوى">
            </label>
        </div>
    </section>

    {{-- ── الكاتب ── --}}
    <section class="admin-panel">
        <div class="admin-panel-head"><h2>الكاتب</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>اسم الكاتب</span>
                <input class="admin-input" name="author_name" value="{{ old('author_name', $post->author_name) }}" maxlength="100" placeholder="خالد سعد">
            </label>
            <label class="admin-field">
                <span>لقب الكاتب</span>
                <input class="admin-input" name="author_title" value="{{ old('author_title', $post->author_title) }}" maxlength="150" placeholder="استراتيجي تسويقي">
            </label>
        </div>
    </section>

    {{-- ── الصور ── --}}
    <section class="admin-panel">
        <div class="admin-panel-head"><h2>الصور</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>الصورة المميزة</span>
                <input class="admin-input" type="file" name="featured_image" accept="image/*">
                @if($post->featured_image)
                    <small class="block mt-1 text-gray-400">الحالية: {{ $post->featured_image }}</small>
                @endif
            </label>
            <label class="admin-field">
                <span>نص بديل للصورة المميزة (alt)</span>
                <input class="admin-input" name="featured_image_alt" value="{{ old('featured_image_alt', $post->featured_image_alt) }}" maxlength="200">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>صورة OG (رابط كامل أو مسار — للمشاركة الاجتماعية)</span>
                <input class="admin-input" name="og_image" value="{{ old('og_image', $post->og_image) }}" maxlength="500" dir="ltr" placeholder="https://...">
            </label>
        </div>
    </section>

    {{-- ── SEO ── --}}
    <section class="admin-panel">
        <div class="admin-panel-head"><h2>SEO</h2></div>
        <div class="admin-form-grid cols-1">
            <label class="admin-field">
                <span>وصف ميتا (meta description)</span>
                <textarea class="admin-input" name="meta_description" rows="3" maxlength="500">{{ old('meta_description', $post->meta_description) }}</textarea>
            </label>
        </div>
    </section>

    {{-- ── النشر والترتيب ── --}}
    <section class="admin-panel">
        <div class="admin-panel-head"><h2>النشر والترتيب</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>تاريخ النشر</span>
                <input class="admin-input" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
            </label>
            <label class="admin-field">
                <span>ترتيب العرض</span>
                <input class="admin-input" type="number" name="sort_order" value="{{ old('sort_order', $post->sort_order ?? 0) }}" min="0">
            </label>
            <label class="admin-field">
                <span class="flex items-center gap-2">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))>
                    منشور
                </span>
            </label>
            <label class="admin-field">
                <span class="flex items-center gap-2">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))>
                    مقال مميز (Featured)
                </span>
            </label>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg">حفظ</button>
            <a href="{{ route('admin.blog-posts.index') }}" class="btn btn-ghost btn-lg">إلغاء</a>
        </div>
    </section>

</form>
@endsection
