@extends('layouts.admin', ['title' => 'دراسة حالة', 'pageTitle' => $caseStudy->exists ? 'تعديل دراسة' : 'دراسة جديدة', 'pageKicker' => 'Case'])

@section('content')
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head"><h2>البيانات</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>العنوان</span>
                <input class="admin-input" name="title" value="{{ old('title', $caseStudy->title) }}" required maxlength="200">
            </label>
            <label class="admin-field">
                <span>slug</span>
                <input class="admin-input" name="slug" value="{{ old('slug', $caseStudy->slug) }}" required maxlength="160">
            </label>
            <label class="admin-field">
                <span>اسم العميل / المشروع</span>
                <input class="admin-input" name="client_name" value="{{ old('client_name', $caseStudy->client_name) }}" required maxlength="160">
            </label>
            <label class="admin-field">
                <span>القطاع</span>
                <input class="admin-input" name="industry" value="{{ old('industry', $caseStudy->industry) }}" maxlength="120">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>ملخص</span>
                <textarea class="admin-input" name="summary" rows="3" required maxlength="1000">{{ old('summary', $caseStudy->summary) }}</textarea>
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>المحتوى (HTML)</span>
                <textarea class="admin-input" name="body_html" rows="16" required>{{ old('body_html', $caseStudy->body_html) }}</textarea>
            </label>
            <label class="admin-field">
                <span>صورة غلاف</span>
                <input class="admin-input" type="file" name="cover_image" accept="image/*">
                @if($caseStudy->cover_image)
                    <small class="block mt-1">{{ $caseStudy->cover_image }}</small>
                @endif
            </label>
            <label class="admin-field">
                <span>ترتيب العرض</span>
                <input class="admin-input" type="number" name="sort_order" value="{{ old('sort_order', $caseStudy->sort_order) }}" min="0">
            </label>
            <label class="admin-field">
                <span>تاريخ النشر</span>
                <input class="admin-input" type="datetime-local" name="published_at" value="{{ old('published_at', $caseStudy->published_at?->format('Y-m-d\TH:i')) }}">
            </label>
            <label class="admin-field">
                <span><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $caseStudy->is_published))> منشور</span>
            </label>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg">حفظ</button>
            <a href="{{ route('admin.case-studies.index') }}" class="btn btn-ghost btn-lg">إلغاء</a>
        </div>
    </section>
</form>
@endsection
