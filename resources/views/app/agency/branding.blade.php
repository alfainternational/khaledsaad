@extends('layouts.app', ['title' => 'علامة الوكالة', 'pageTitle' => 'العلامة البيضاء', 'pageKicker' => 'الوكالة'])

@section('content')
<section class="studio-gen-header mb-6">
    <div>
        <h2 class="heading-lg">العلامة البيضاء (White-label)</h2>
        <p class="text-muted">خصّص علامتك على المخرجات المواجهة للعميل: الاسم، اللون، والشعار.</p>
    </div>
    <div class="studio-gen-actions">
        <a href="{{ route('agency.index') }}" class="btn btn-secondary">لوحة الوكالة</a>
    </div>
</section>

@if (session('status'))
    <div class="dx-alert dx-alert--success mb-6">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="dx-alert mb-6"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('agency.branding.update') }}" class="card dx-form dx-form-narrow">
    @csrf @method('PATCH')

    <label class="dx-field">
        <span>تفعيل العلامة البيضاء</span>
        <select name="enabled" class="dx-select">
            <option value="1" @selected(($branding['enabled'] ?? false))>مفعّلة</option>
            <option value="0" @selected(!($branding['enabled'] ?? false))>غير مفعّلة</option>
        </select>
    </label>

    <label class="dx-field">
        <span>اسم الوكالة (يظهر للعميل)</span>
        <input class="dx-input" type="text" name="name" value="{{ old('name', $branding['name'] ?? $workspace->name) }}" maxlength="120">
    </label>

    <label class="dx-field">
        <span>لون العلامة (hex)</span>
        <input class="dx-input" type="text" name="color" value="{{ old('color', $branding['color'] ?? '#6366f1') }}" placeholder="#6366f1">
    </label>

    <label class="dx-field">
        <span>رابط الشعار (اختياري)</span>
        <input class="dx-input" type="url" name="logo_url" value="{{ old('logo_url', $branding['logo_url'] ?? '') }}" placeholder="https://...">
    </label>

    <button type="submit" class="dx-submit">حفظ العلامة</button>
</form>
@endsection
