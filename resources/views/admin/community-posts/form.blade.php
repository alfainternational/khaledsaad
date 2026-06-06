@extends('layouts.admin', ['title' => 'موضوع مجتمع', 'pageTitle' => $post->exists ? 'تعديل موضوع' : 'موضوع جديد', 'pageKicker' => 'Community'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head"><h2>المحتوى</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>العنوان</span>
                <input class="admin-input" name="title" value="{{ old('title', $post->title) }}" required maxlength="200">
            </label>
            <label class="admin-field">
                <span>slug</span>
                <input class="admin-input" name="slug" value="{{ old('slug', $post->slug) }}" required maxlength="160">
            </label>
            <label class="admin-field">
                <span>اسم العرض (مؤلف)</span>
                <input class="admin-input" name="author_display_name" value="{{ old('author_display_name', $post->author_display_name) }}" maxlength="120">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>مقتطف</span>
                <textarea class="admin-input" name="excerpt" rows="2" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea>
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>المحتوى (HTML)</span>
                <textarea class="admin-input" name="body_html" rows="16" required>{{ old('body_html', $post->body_html) }}</textarea>
            </label>
            <label class="admin-field">
                <span>تاريخ النشر</span>
                <input class="admin-input" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
            </label>
            <label class="admin-field">
                <span><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))> منشور</span>
            </label>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg">حفظ</button>
            <a href="{{ route('admin.community-posts.index') }}" class="btn btn-ghost btn-lg">إلغاء</a>
        </div>
    </section>
</form>
@endsection
