@extends('layouts.admin', ['title' => 'صفحة CMS', 'pageTitle' => $page->exists ? 'تعديل صفحة' : 'صفحة جديدة', 'pageKicker' => 'CMS'])

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
                <span>slug</span>
                <input class="admin-input" name="slug" value="{{ old('slug', $page->slug) }}" required maxlength="120">
            </label>
            <label class="admin-field">
                <span>العنوان</span>
                <input class="admin-input" name="title" value="{{ old('title', $page->title) }}" required maxlength="200">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>العنوان الفرعي</span>
                <input class="admin-input" name="subtitle" value="{{ old('subtitle', $page->subtitle) }}" maxlength="255">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>وصف ميتا (SEO)</span>
                <input class="admin-input" name="meta_description" value="{{ old('meta_description', $page->meta_description) }}" maxlength="500">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>المحتوى (HTML)</span>
                <textarea class="admin-input" name="body_html" rows="18">{{ old('body_html', $page->body_html) }}</textarea>
            </label>
            <label class="admin-field">
                <span><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published))> منشور</span>
            </label>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg">حفظ</button>
            <a href="{{ route('admin.cms-pages.index') }}" class="btn btn-ghost btn-lg">إلغاء</a>
        </div>
    </section>
</form>
@endsection
