@extends('layouts.app')
@section('layout', 'form')
@section('title', $content->exists ? 'تعديل المحتوى' : 'محتوى جديد')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المكتبة · المحرر</p>
            <h1>{{ $content->exists ? 'تعديل: '.$content->title : 'محتوى جديد' }}</h1>
            <p class="muted">المحتوى مجاني بشكل افتراضي. ويمكنك جعله متاحًا بعد تسجيل البريد.</p>
        </div>
        <a href="{{ route('admin.content.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    @if ($errors->any()) <div class="alert alert--error">{{ $errors->first() }}</div> @endif
    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="POST" action="{{ $content->exists ? route('admin.content.update', $content) : route('admin.content.store') }}" class="form content-form content-form--fluid" data-content-form>
        @csrf
        @if ($content->exists) @method('PUT') @endif

        <div class="field-row">
            <label class="field">
                <span class="field__label">النوع</span>
                <select name="type" required>
                    @foreach (['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $content->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">العنوان</span>
                <input type="text" name="title" value="{{ old('title', $content->title) }}" required>
            </label>
            <label class="field">
                <span class="field__label">الرابط المختصر</span>
                <input type="text" name="slug" value="{{ old('slug', $content->slug) }}" dir="ltr" required>
            </label>
        </div>

        <label class="field">
            <span class="field__label">الملخص</span>
            <textarea name="excerpt" rows="3">{{ old('excerpt', $content->excerpt) }}</textarea>
        </label>

        <section class="content-editor-shell" data-content-editor data-upload-url="{{ route('admin.content.media.store') }}">
            <div class="content-editor-toolbar" data-editor-toolbar></div>
            <div class="content-editor-area" data-editor-area></div>
            <div class="content-editor-status"><span data-editor-count>0 كلمة</span><button type="button" class="btn btn--ghost btn--sm" data-editor-preview>معاينة</button></div>
        </section>
        <textarea name="body_html" data-editor-html hidden>{{ old('body_html', $content->body_html) }}</textarea>
        <input type="hidden" name="body_json" data-editor-json value="{{ old('body_json', $content->body_json ? json_encode($content->body_json, JSON_UNESCAPED_UNICODE) : '') }}">

        <div class="field-row">
            <label class="field"><span class="field__label">صورة الغلاف</span><input type="text" name="cover_image_path" value="{{ old('cover_image_path', $content->cover_image_path) }}" dir="ltr"></label>
            <label class="field"><span class="field__label">رابط الفيديو</span><input type="url" name="video_url" value="{{ old('video_url', $content->video_url) }}" dir="ltr"></label>
            <label class="field"><span class="field__label">المدة بالدقائق</span><input type="number" name="duration_minutes" min="1" value="{{ old('duration_minutes', $content->duration_minutes) }}"></label>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field__label">الحالة</span>
                <select name="status">
                    @foreach (['draft' => 'مسودة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $content->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">الوصول</span>
                <select name="access_level">
                    <option value="public" @selected(old('access_level', $content->access_level) === 'public')>مجاني للجميع</option>
                    <option value="subscribers" @selected(old('access_level', $content->access_level) === 'subscribers')>بعد تسجيل البريد</option>
                </select>
            </label>
            <label class="field"><span class="field__label">موعد النشر</span><input type="datetime-local" name="published_at" value="{{ old('published_at', $content->published_at?->format('Y-m-d\TH:i')) }}"></label>
        </div>

        <fieldset class="form-section">
            <legend>تهيئة محركات البحث</legend>
            <label class="field"><span class="field__label">عنوان SEO</span><input type="text" name="seo_title" value="{{ old('seo_title', $content->seo_title) }}"></label>
            <label class="field"><span class="field__label">وصف SEO</span><textarea name="seo_description" rows="2">{{ old('seo_description', $content->seo_description) }}</textarea></label>
        </fieldset>

        <input type="hidden" name="sort_order" value="{{ old('sort_order', $content->sort_order ?? 0) }}">
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">حفظ المحتوى</button>
            @if ($content->exists && $content->type === 'course')
                <a href="{{ route('admin.content.curriculum', $content) }}" class="btn btn--ghost">إدارة منهج الدورة</a>
            @endif
        </div>
    </form>
@endsection
