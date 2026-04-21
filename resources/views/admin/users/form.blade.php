@extends('layouts.admin', ['title' => 'المستخدم', 'pageTitle' => $managedUser->exists ? 'تعديل المستخدم' : 'إنشاء مستخدم', 'pageKicker' => 'Users'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>بيانات المستخدم</h2>
        </div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>الاسم</span>
                <input class="admin-input" name="name" value="{{ old('name', $managedUser->name) }}">
            </label>
            <label class="admin-field">
                <span>البريد الإلكتروني</span>
                <input class="admin-input" type="email" name="email" value="{{ old('email', $managedUser->email) }}">
            </label>
            <label class="admin-field">
                <span>{{ $managedUser->exists ? 'كلمة مرور جديدة' : 'كلمة المرور' }}</span>
                <input class="admin-input" type="password" name="password" autocomplete="new-password">
            </label>
            <label class="admin-field">
                <span>اللغة</span>
                <select class="admin-input" name="locale">
                    @foreach (['ar' => 'العربية', 'en' => 'English'] as $locale => $label)
                        <option value="{{ $locale }}" @selected(old('locale', $managedUser->locale ?? 'ar') === $locale)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>الحالة</span>
                <select class="admin-input" name="status">
                    @foreach (['active', 'frozen'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $managedUser->status?->value ?? $managedUser->status ?? 'active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>الصلاحية الإدارية العليا</span>
                <input type="checkbox" name="is_super_admin" value="1" @checked(old('is_super_admin', $managedUser->is_super_admin))>
            </label>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $managedUser->exists ? 'حفظ التعديلات' : 'إنشاء المستخدم' }}</button>
        <a href="{{ $managedUser->exists ? route('admin.users.show', $managedUser) : route('admin.users.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection
