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
                <span class="field__label">القسم</span>
                <select name="category_id">
                    <option value="">غير مصنف</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $content->category_id) === (string) $category->id)>{{ $category->name }}</option>
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

        <section class="content-editor-shell" data-content-editor data-upload-url="{{ route('admin.content.media.store') }}" data-media-max-bytes="{{ config('content.media.max_bytes', 268435456) }}">
            <div class="content-editor-intro">
                <div><strong>محرر المحتوى</strong><span>حدّد النص ثم اختر الأيقونة المناسبة. مرّر المؤشر فوق أي أداة لمعرفة وظيفتها.</span></div>
                <span class="content-editor-intro__hint">يدعم الصور والفيديو والجداول والمهام</span>
            </div>
            <div class="content-editor-toolbar" data-editor-toolbar role="toolbar" aria-label="أدوات تحرير المحتوى"></div>
            <div class="content-editor-area" data-editor-area></div>
            <div class="content-editor-status">
                <span data-editor-count>0 كلمة</span>
                <button type="button" class="btn btn--ghost btn--sm content-editor-preview-button" data-editor-preview>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <span>معاينة</span>
                </button>
            </div>
        </section>
        <textarea name="body_html" data-editor-html hidden>{{ old('body_html', $content->body_html) }}</textarea>
        <input type="hidden" name="body_json" data-editor-json value="{{ old('body_json', $content->body_json ? json_encode($content->body_json, JSON_UNESCAPED_UNICODE) : '') }}">

        @php
            $coverValue = old('cover_image_path', $content->cover_image_path);
            $coverPreview = $coverValue
                ? (str_starts_with($coverValue, 'http') || str_starts_with($coverValue, '/')
                    ? $coverValue
                    : \Illuminate\Support\Facades\Storage::disk('public')->url($coverValue))
                : '';
        @endphp

        <section class="content-cover" data-content-cover data-upload-url="{{ route('admin.content.media.store') }}" data-preview-url="{{ $coverPreview }}" data-media-max-bytes="{{ config('content.media.max_bytes', 268435456) }}">
            <div class="content-cover__head">
                <div>
                    <h2>الصورة الرئيسية</h2>
                    <p class="muted">تظهر في بطاقة المادة ورأس صفحة القراءة وخلفية العنوان. أفضل ظهور عند 1600 × 900 بكسل، وتُقبل كل الأبعاد حتى 256 ميجابايت.</p>
                </div>
                <span class="content-cover__badge">16:9</span>
            </div>
            <div class="content-cover__layout">
                <div class="content-cover__preview">
                    <img src="{{ $coverPreview }}" alt="معاينة الصورة الرئيسية" data-cover-preview @if(! $coverPreview) hidden @endif>
                    <div class="content-cover__empty" data-cover-empty @if($coverPreview) hidden @endif>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m5 18 4-4 3 3 3-4 4 5"/></svg>
                        <strong>أضف صورة تجذب القارئ</strong>
                        <span>JPG أو PNG أو WebP أو GIF · حتى 256 ميجابايت</span>
                    </div>
                </div>
                <div class="content-cover__controls">
                    <label class="btn btn--primary">
                        <span>رفع صورة من الجهاز</span>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-cover-file hidden>
                    </label>
                    <button type="button" class="btn btn--ghost" data-cover-remove @if(! $coverValue) hidden @endif>حذف الصورة</button>
                    <p class="content-cover__status" data-cover-status role="status" aria-live="polite"></p>
                    <input type="hidden" name="cover_image_path" value="{{ $coverValue }}" data-cover-input>
                </div>
            </div>
        </section>

        <div class="field-row">
            <label class="field"><span class="field__label">رابط الفيديو</span><input type="url" name="video_url" value="{{ old('video_url', $content->video_url) }}" dir="ltr"></label>
            <label class="field"><span class="field__label">المدة بالدقائق</span><input type="number" name="duration_minutes" min="1" value="{{ old('duration_minutes', $content->duration_minutes) }}"></label>
        </div>

        @php
            $savedResources = $content->exists
                ? $content->resources()->with('media')->get()->map(fn ($resource) => [
                    'type' => $resource->type,
                    'title' => $resource->title,
                    'media_id' => $resource->content_media_id,
                    'url' => $resource->type === 'file' ? $resource->media?->url() : $resource->url,
                    'original_name' => $resource->media?->original_name,
                    'size_bytes' => $resource->media?->size_bytes,
                    'mime_type' => $resource->media?->mime_type,
                ])->values()->all()
                : [];
            $oldResourcesJson = old('resources_json');
            $initialResources = is_string($oldResourcesJson)
                ? (json_decode($oldResourcesJson, true) ?: [])
                : $savedResources;
        @endphp

        <section class="content-resources" data-content-resources data-upload-url="{{ route('admin.content.media.store') }}" data-media-max-bytes="{{ config('content.media.max_bytes', 268435456) }}">
            <div class="content-resources__head">
                <div>
                    <h2>المواد المصاحبة</h2>
                    <p class="muted">ارفع ملفات الدرس أو أضف روابط خارجية. يمكنك إضافة أكثر من مادة وترتيبها، حتى 256 ميجابايت لكل ملف.</p>
                </div>
                <label class="btn btn--ghost content-resources__upload">
                    <span>رفع ملفات من الجهاز</span>
                    <input type="file" multiple data-resource-files accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.zip,.jpg,.jpeg,.png,.webp,.gif,.mp3,.wav,.m4a,.ogg,.mp4,.webm" hidden>
                </label>
            </div>

            <div class="content-resources__link" data-resource-link-form>
                <label class="field">
                    <span class="field__label">اسم الرابط</span>
                    <input type="text" data-resource-link-title maxlength="255" placeholder="مثال: المرجع الإضافي">
                </label>
                <label class="field">
                    <span class="field__label">الرابط</span>
                    <input type="url" data-resource-link-url dir="ltr" placeholder="https://example.com">
                </label>
                <button type="button" class="btn btn--ghost" data-resource-add-link>إضافة الرابط</button>
            </div>

            <p class="content-resources__status" data-resource-status role="status" aria-live="polite"></p>
            <ol class="content-resources__list" data-resource-list></ol>
            <p class="empty-state content-resources__empty" data-resource-empty>لا توجد مواد مصاحبة بعد.</p>
            <input type="hidden" name="resources_json" data-resources-json value="{{ old('resources_json', json_encode($savedResources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}">
            <script type="application/json" data-resources-initial>{!! json_encode($initialResources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        </section>

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
