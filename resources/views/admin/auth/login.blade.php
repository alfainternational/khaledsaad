@extends('layouts.admin', ['title' => 'دخول الإدارة'])

@section('content')
<div class="admin-auth-wrap">
    <div class="admin-auth-card">
        <div class="section-badge mb-5">
            <span class="section-dot"></span>
            <span class="section-badge-text">دخول إداري</span>
        </div>

        <h1 class="heading-md mb-4">تسجيل الدخول إلى لوحة الإدارة</h1>
        <p class="text-body mb-6">استخدم بريد المدير العام وكلمة المرور للوصول إلى أدوات الإدارة والتحكم.</p>

        <form method="POST" action="{{ route('admin.login.store') }}" class="admin-form-stack">
            @csrf
            <label class="admin-field">
                <span>البريد الإلكتروني</span>
                <input type="email" name="email" value="{{ old('email') }}" required class="admin-input">
            </label>

            <label class="admin-field">
                <span>كلمة المرور</span>
                <input type="password" name="password" required class="admin-input">
            </label>

            <button type="submit" class="btn btn-primary btn-xl">دخول اللوحة</button>
        </form>
    </div>
</div>
@endsection
