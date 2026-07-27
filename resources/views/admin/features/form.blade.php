@extends('layouts.app')

@section('title', $feature->exists ? 'تعديل عنصر ميزة' : 'عنصر ميزة جديد')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · فهرس الميزات</p>
            <h1>{{ $feature->exists ? 'تعديل: '.$feature->name : 'عنصر ميزة جديد' }}</h1>
            @if ($locked)
                <p class="muted">هذا العنصر مربوط بنقطة منع في النظام: المفتاح والنوع والتطبيق ثابتة، وما عداها قابل للتحرير.</p>
            @else
                <p class="muted">العناصر المضافة من هنا عرضية: تظهر في صفحة الأسعار ولا يمنعها النظام تقنيًا.</p>
            @endif
        </div>
        <a href="{{ route('admin.features.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ $feature->exists ? route('admin.features.update', $feature) : route('admin.features.store') }}" class="form form--wide">
        @csrf
        @if ($feature->exists) @method('PUT') @endif

        <div class="field-row">
            <label class="field">
                <span class="field__label">المفتاح (إنجليزي)</span>
                <input type="text" name="key" value="{{ old('key', $feature->key) }}" required @disabled($locked)>
                @if ($locked)
                    <input type="hidden" name="key" value="{{ $feature->key }}">
                @endif
            </label>
            <label class="field">
                <span class="field__label">الاسم الظاهر</span>
                <input type="text" name="name" value="{{ old('name', $feature->name) }}" required>
            </label>
        </div>

        <label class="field">
            <span class="field__label">الوصف</span>
            <input type="text" name="description" value="{{ old('description', $feature->description) }}">
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">المجموعة</span>
                <select name="group">
                    @foreach (['core' => 'الأساس', 'reports' => 'التقارير', 'growth' => 'محرك النمو', 'support' => 'الدعم والخدمة', 'general' => 'عام'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('group', $feature->group) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">النوع</span>
                <select name="type" @disabled($locked)>
                    <option value="boolean" @selected(old('type', $feature->type) === 'boolean')>تشغيل/إيقاف</option>
                    <option value="limit" @selected(old('type', $feature->type) === 'limit')>حد أقصى</option>
                    <option value="quota" @selected(old('type', $feature->type) === 'quota')>حصة شهرية</option>
                </select>
                @if ($locked)
                    <input type="hidden" name="type" value="{{ $feature->type }}">
                @endif
            </label>
            <label class="field">
                <span class="field__label">الوحدة (مشروع، تشغيل/شهر…)</span>
                <input type="text" name="unit" value="{{ old('unit', $feature->unit) }}">
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                {{-- التطبيق يقرره الكود لا اللوحة: عرض للعلم فقط. --}}
                <span class="field__label">التطبيق</span>
                <select disabled>
                    <option @selected(($feature->enforcement ?: 'display') === 'gate')>مُطبَّق (يمنع فعليًا)</option>
                    <option @selected(($feature->enforcement ?: 'display') === 'display')>عرضي (تسويقي)</option>
                </select>
                <input type="hidden" name="enforcement" value="{{ $feature->enforcement ?: 'display' }}">
            </label>
            <label class="field">
                <span class="field__label">القيمة الافتراضية (فارغ = بلا حد)</span>
                <input type="number" name="default_value" value="{{ old('default_value', $feature->default_value) }}" min="0">
            </label>
            <label class="field">
                <span class="field__label">الترتيب</span>
                <input type="number" name="sort_order" value="{{ old('sort_order', $feature->sort_order ?? 0) }}" min="0">
            </label>
        </div>

        <label class="field field--inline">
            <input type="checkbox" name="default_enabled" value="1" @checked(old('default_enabled', $feature->default_enabled))>
            <span>مفتوح افتراضيًا للخطط التي لم تُحدِّده صراحة</span>
        </label>

        <label class="field field--inline">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $feature->exists ? $feature->is_active : true))>
            <span>مفعّل في الفهرس</span>
        </label>

        <button type="submit" class="btn btn--primary">{{ $feature->exists ? 'حفظ' : 'إضافة' }}</button>
    </form>
@endsection
