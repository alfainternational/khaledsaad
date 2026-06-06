@extends('layouts.admin', ['title' => 'عرض قالب', 'pageTitle' => $item->exists ? 'تعديل' : 'جديد', 'pageKicker' => 'Marketing'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head"><h2>البيانات</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>العنوان</span>
                <input class="admin-input" name="title" value="{{ old('title', $item->title) }}" required maxlength="200">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>وصف قصير</span>
                <textarea class="admin-input" name="description" rows="3" required maxlength="1000">{{ old('description', $item->description) }}</textarea>
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>تفاصيل (HTML اختياري)</span>
                <textarea class="admin-input" name="body_html" rows="8">{{ old('body_html', $item->body_html) }}</textarea>
            </label>
            <label class="admin-field">
                <span>الفئة</span>
                <input class="admin-input" name="category" value="{{ old('category', $item->category) }}" maxlength="120">
            </label>
            <label class="admin-field">
                <span>أيقونة (إيموجي)</span>
                <input class="admin-input" name="icon_emoji" value="{{ old('icon_emoji', $item->icon_emoji) }}" maxlength="20">
            </label>
            <label class="admin-field">
                <span>نص زر CTA</span>
                <input class="admin-input" name="cta_label" value="{{ old('cta_label', $item->cta_label) }}" maxlength="80">
            </label>
            <label class="admin-field">
                <span>رابط CTA</span>
                <input class="admin-input" name="cta_url" value="{{ old('cta_url', $item->cta_url) }}" maxlength="500" placeholder="/login">
            </label>
            <label class="admin-field">
                <span>ترتيب</span>
                <input class="admin-input" type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order) }}" min="0">
            </label>
            <label class="admin-field">
                <span><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $item->is_published))> منشور</span>
            </label>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg">حفظ</button>
            <a href="{{ route('admin.marketing-template-highlights.index') }}" class="btn btn-ghost btn-lg">إلغاء</a>
        </div>
    </section>
</form>
@endsection
