@extends('layouts.app')
@section('layout', 'form')

@section('title', $pack->exists ? 'تعديل حزمة' : 'حزمة جديدة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · حزم الأرصدة</p>
            <h1>{{ $pack->exists ? 'تعديل: '.$pack->name : 'حزمة جديدة' }}</h1>
        </div>
        <a href="{{ route('admin.packs.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ $pack->exists ? route('admin.packs.update', $pack) : route('admin.packs.store') }}" class="form form--wide">
        @csrf
        @if ($pack->exists) @method('PUT') @endif

        <label class="field">
            <span class="field__label">اسم الحزمة</span>
            <input type="text" name="name" value="{{ old('name', $pack->name) }}" required>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">عدد الأرصدة</span>
                <input type="number" name="credits" value="{{ old('credits', $pack->credits ?? 20) }}" min="1" required>
            </label>
            <label class="field">
                <span class="field__label">السعر</span>
                <input type="number" name="price" value="{{ old('price', $pack->price ?? 25) }}" min="0" required>
            </label>
            <label class="field">
                <span class="field__label">العملة</span>
                <input type="text" name="currency" value="{{ old('currency', $pack->currency ?? 'SAR') }}" maxlength="3" required>
            </label>
        </div>

        <label class="field">
            <span class="field__label">الترتيب</span>
            <input type="number" name="sort_order" value="{{ old('sort_order', $pack->sort_order ?? 0) }}" min="0">
        </label>

        <label class="field field--inline">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pack->is_active ?? true))>
            <span>مفعّلة للبيع</span>
        </label>

        <button type="submit" class="btn btn--primary">{{ $pack->exists ? 'حفظ' : 'إنشاء' }}</button>
    </form>
@endsection
