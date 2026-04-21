@extends('layouts.admin', ['title' => 'الأداة', 'pageTitle' => $tool->exists ? 'تعديل الأداة' : 'إنشاء أداة', 'pageKicker' => 'Tools'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <section class="admin-panel">
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>الكود</span>
                <input class="admin-input" name="code" value="{{ old('code', $tool->code) }}">
            </label>
            <label class="admin-field">
                <span>الاسم</span>
                <input class="admin-input" name="name" value="{{ old('name', $tool->name) }}">
            </label>
            <label class="admin-field cols-span-2">
                <span>الوصف</span>
                <textarea class="admin-input" name="description" rows="4">{{ old('description', $tool->description) }}</textarea>
            </label>
            <label class="admin-field">
                <span>الموديول</span>
                <select class="admin-input" name="module">
                    <option value="">بدون ربط</option>
                    @foreach ($modules as $key => $module)
                        <option value="{{ $key }}" @selected(old('module', $tool->module) === $key)>{{ $module['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>المرحلة</span>
                <select class="admin-input" name="stage">
                    @foreach ([1, 2, 3, 4, 5] as $stage)
                        <option value="{{ $stage }}" @selected((int) old('stage', $tool->stage ?? 1) === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>الترتيب</span>
                <input class="admin-input" type="number" name="sort_order" value="{{ old('sort_order', $tool->sort_order ?? 0) }}">
            </label>
            <label class="admin-field">
                <span>الحالة</span>
                <select class="admin-input" name="status">
                    @foreach (['draft', 'published', 'beta', 'hidden'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $tool->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $tool->exists ? 'حفظ التعديلات' : 'إنشاء الأداة' }}</button>
        <a href="{{ route('admin.tools.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>

@if (isset($blueprintPreview) && $blueprintPreview)
    <section class="admin-panel mt-4">
        <div class="admin-panel-head">
            <h2>معاينة تجربة الأداة</h2>
        </div>
        @if (isset($blueprintFound) && ! $blueprintFound)
            <div class="app-alert warning">هذا الكود لا يطابق Blueprint معرفاً حالياً، وسيتم استخدام القالب الاحتياطي في واجهة المستخدم.</div>
        @endif
        <div class="app-list">
            <div class="app-list-item">
                <div>
                    <strong>نوع المخرج</strong>
                    <small>{{ $blueprintPreview['result_label'] ?? 'غير محدد' }}</small>
                </div>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>مقدمة الأداة</strong>
                    <small>{{ $blueprintPreview['intro'] ?? 'لا توجد مقدمة' }}</small>
                </div>
            </div>
        </div>
    </section>
@endif
@endsection
