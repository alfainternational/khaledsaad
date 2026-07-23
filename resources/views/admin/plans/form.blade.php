@extends('layouts.app')

@section('title', $plan->exists ? 'تعديل خطة' : 'خطة جديدة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · الخطط</p>
            <h1>{{ $plan->exists ? 'تعديل: '.$plan->name : 'خطة جديدة' }}</h1>
        </div>
        <a href="{{ route('admin.plans.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="form form--wide">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div class="field-row">
            <label class="field">
                <span class="field__label">المفتاح (إنجليزي)</span>
                <input type="text" name="key" value="{{ old('key', $plan->key) }}" required>
            </label>
            <label class="field">
                <span class="field__label">الاسم</span>
                <input type="text" name="name" value="{{ old('name', $plan->name) }}" required>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field__label">السعر (ريال)</span>
                <input type="number" name="price" value="{{ old('price', $plan->price ?? 0) }}" min="0" required>
            </label>
            <label class="field">
                <span class="field__label">الدورة</span>
                <select name="interval">
                    <option value="monthly" @selected(old('interval', $plan->interval) === 'monthly')>شهرية</option>
                    <option value="yearly" @selected(old('interval', $plan->interval) === 'yearly')>سنوية</option>
                </select>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field__label">الرصيد الشهري</span>
                <input type="number" name="monthly_credits" value="{{ old('monthly_credits', $plan->monthly_credits ?? 0) }}" min="0" required>
            </label>
            <label class="field">
                <span class="field__label">حد المشاريع</span>
                <input type="number" name="project_limit" value="{{ old('project_limit', $plan->project_limit ?? 1) }}" min="1" required>
            </label>
            <label class="field">
                <span class="field__label">الترتيب</span>
                <input type="number" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0">
            </label>
        </div>

        <label class="field">
            <span class="field__label">الميزات (ميزة في كل سطر)</span>
            <textarea name="features" rows="4">{{ old('features', implode("\n", $plan->features ?? [])) }}</textarea>
        </label>

        <label class="field field--inline">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $plan->is_public ?? true))>
            <span>ظاهرة للعملاء</span>
        </label>

        <button type="submit" class="btn btn--primary">{{ $plan->exists ? 'حفظ' : 'إنشاء' }}</button>
    </form>
@endsection
