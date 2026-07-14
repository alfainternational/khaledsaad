@extends('layouts.marketing', ['title' => 'إنشاء حساب', 'description' => 'إنشاء حساب جديد في منصة التسويق الاستراتيجي'])

@section('content')
<section class="dx">
    <div class="dx-shell">
        <div class="dx-hero-head">
            <span class="dx-chip">حساب جديد</span>
            <h1 class="dx-title">أنشئ حسابك وابدأ من مساحة عملك الأولى</h1>
            <p class="dx-sub">سننشئ لك حساباً، واشتراك Free، ومساحة عمل شخصية جاهزة لتبدأ مباشرة.</p>
        </div>

        @if ($errors->any())
            <div class="dx-alert" role="alert">
                <strong>تعذّر إنشاء الحساب.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="dx-card dx-form">
            @csrf

            <div class="dx-grid-2">
                <label class="dx-field">
                    <span>الاسم الكامل</span>
                    <input class="dx-input" type="text" name="name" value="{{ old('name') }}" required autofocus>
                </label>
                <label class="dx-field">
                    <span>البريد الإلكتروني</span>
                    <input class="dx-input" type="email" name="email" value="{{ old('email') }}" required>
                </label>
            </div>

            <div class="dx-grid-2">
                <label class="dx-field">
                    <span>اسم الحساب التجاري (اختياري)</span>
                    <input class="dx-input" type="text" name="account_name" value="{{ old('account_name') }}" placeholder="مثال: شركة المسار">
                </label>
                <label class="dx-field">
                    <span>اسم مساحة العمل (اختياري)</span>
                    <input class="dx-input" type="text" name="workspace_name" value="{{ old('workspace_name') }}" placeholder="مثال: فريق التسويق">
                </label>
            </div>

            <div class="dx-grid-2">
                <label class="dx-field">
                    <span>كلمة المرور</span>
                    <input class="dx-input" type="password" name="password" required>
                </label>
                <label class="dx-field">
                    <span>تأكيد كلمة المرور</span>
                    <input class="dx-input" type="password" name="password_confirmation" required>
                </label>
            </div>

            <button type="submit" class="dx-submit">إنشاء الحساب</button>
        </form>

        @include('auth._social-buttons')

        <p class="dx-note">لديك حساب بالفعل؟ <a href="{{ route('login') }}">سجّل الدخول</a></p>
    </div>
</section>
@endsection
