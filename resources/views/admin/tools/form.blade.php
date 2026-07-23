@extends('layouts.app')

@section('title', $tool ? 'تعديل أداة' : 'أداة جديدة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · الأدوات</p>
            <h1>{{ $tool ? 'تعديل: '.$tool->title : 'أداة جديدة' }}</h1>
            <p class="muted">البيانات البنيوية (الحقول، المخطط، الدرجة) تُحرَّر كـJSON. أي خطأ صياغة يُرفض عند الحفظ.</p>
        </div>
        <a href="{{ route('admin.tools.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ $tool ? route('admin.tools.update', $tool) : route('admin.tools.store') }}" class="form form--wide">
        @csrf
        @if ($tool) @method('PUT') @endif

        <div class="field-row">
            <label class="field">
                <span class="field__label">المفتاح (إنجليزي)</span>
                <input type="text" name="key" value="{{ old('key', $defaults['key']) }}" @readonly($tool !== null) required>
            </label>
            <label class="field">
                <span class="field__label">الاسم الداخلي</span>
                <input type="text" name="name" value="{{ old('name', $defaults['name']) }}" required>
            </label>
        </div>

        <label class="field">
            <span class="field__label">العنوان الظاهر (وجع العميل)</span>
            <input type="text" name="title" value="{{ old('title', $defaults['title']) }}" required>
        </label>

        <label class="field">
            <span class="field__label">الوصف</span>
            <textarea name="description" rows="2" required>{{ old('description', $defaults['description']) }}</textarea>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">الوجع (pain)</span>
                <input type="text" name="pain" value="{{ old('pain', $defaults['pain']) }}">
            </label>
            <label class="field">
                <span class="field__label">الوعد (promise)</span>
                <input type="text" name="promise" value="{{ old('promise', $defaults['promise']) }}">
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field__label">لمن (audience)</span>
                <input type="text" name="audience" value="{{ old('audience', $defaults['audience']) }}">
            </label>
            <label class="field">
                <span class="field__label">المدة (دقائق)</span>
                <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $defaults['duration_minutes']) }}" min="1">
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field__label">الفئة</span>
                <input type="text" name="category" value="{{ old('category', $defaults['category']) }}" required>
            </label>
            <label class="field">
                <span class="field__label">الترتيب</span>
                <input type="number" name="sort_order" value="{{ old('sort_order', $defaults['sort_order']) }}" min="0">
            </label>
            <label class="field">
                <span class="field__label">تكلفة الرصيد</span>
                <input type="number" name="credit_cost" value="{{ old('credit_cost', $defaults['credit_cost']) }}" min="0" required>
            </label>
        </div>

        <label class="field">
            <span class="field__label">الحالة</span>
            <select name="status">
                <option value="coming_soon" @selected(old('status', $defaults['status']) === 'coming_soon')>قريبًا</option>
                <option value="published" @selected(old('status', $defaults['status']) === 'published')>منشورة</option>
            </select>
        </label>

        <label class="field">
            <span class="field__label">الحقول (JSON — مصفوفة أسئلة)</span>
            <textarea name="fields" rows="10" dir="ltr" class="code-input" required>{{ old('fields', $defaults['fields']) }}</textarea>
        </label>

        <label class="field">
            <span class="field__label">قواعد الدرجة (JSON)</span>
            <textarea name="scoring_rules" rows="8" dir="ltr" class="code-input" required>{{ old('scoring_rules', $defaults['scoring_rules']) }}</textarea>
        </label>

        <label class="field">
            <span class="field__label">خطة الأقسام (JSON)</span>
            <textarea name="section_plan" rows="6" dir="ltr" class="code-input" required>{{ old('section_plan', $defaults['section_plan']) }}</textarea>
        </label>

        <label class="field">
            <span class="field__label">مخطط المخرج (JSON)</span>
            <textarea name="output_schema" rows="8" dir="ltr" class="code-input" required>{{ old('output_schema', $defaults['output_schema']) }}</textarea>
        </label>

        <button type="submit" class="btn btn--primary">{{ $tool ? 'حفظ' : 'إنشاء الأداة' }}</button>
        @if ($tool)
            <p class="muted">البرومبتات تُحرَّر من <a href="{{ route('admin.tools.show', $tool->key) }}">صفحة الأداة</a>.</p>
        @endif
    </form>
@endsection
