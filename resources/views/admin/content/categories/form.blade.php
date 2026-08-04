@extends('layouts.app')
@section('layout', 'form')
@section('title', $category->exists ? 'تعديل القسم' : 'قسم جديد')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المكتبة · الأقسام</p>
            <h1>{{ $category->exists ? 'تعديل: '.$category->name : 'قسم جديد' }}</h1>
            <p class="muted">القسم موضوع جامع، ويمكن أن يضم أنواعًا مختلفة من المحتوى.</p>
        </div>
        <a href="{{ route('admin.content-categories.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    @if ($errors->any()) <div class="alert alert--error">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ $category->exists ? route('admin.content-categories.update', $category) : route('admin.content-categories.store') }}" class="form">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div class="field-row">
            <label class="field"><span class="field__label">اسم القسم</span><input name="name" value="{{ old('name', $category->name) }}" required></label>
            <label class="field"><span class="field__label">الرابط المختصر</span><input name="slug" value="{{ old('slug', $category->slug) }}" dir="ltr" required></label>
        </div>

        <label class="field"><span class="field__label">وصف مختصر</span><textarea name="description" rows="3">{{ old('description', $category->description) }}</textarea></label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">الأيقونة</span>
                <select name="icon" required>
                    @foreach (['folder' => 'مجلد', 'megaphone' => 'تسويق', 'book-open' => 'كتاب', 'graduation-cap' => 'تعليم', 'presentation' => 'محاضرة', 'chart' => 'تحليل', 'lightbulb' => 'أفكار', 'target' => 'أهداف'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('icon', $category->icon ?: 'folder') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field"><span class="field__label">لون القسم</span><input type="color" name="color" value="{{ old('color', $category->color ?: '#2575ff') }}" required></label>
            <label class="field"><span class="field__label">الترتيب</span><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" required></label>
        </div>

        <input type="hidden" name="is_active" value="0">
        <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true))><span>إظهار القسم في المكتبة العامة</span></label>

        <div class="form-actions"><button class="btn btn--primary">حفظ القسم</button></div>
    </form>
@endsection
