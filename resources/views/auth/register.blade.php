@extends('layouts.marketing', ['title' => 'إنشاء حساب', 'description' => 'إنشاء حساب جديد في منصة التسويق الاستراتيجي'])

@section('content')
<section class="section-lg">
    <div class="site-container">
        <div class="auth-shell">
            <div class="auth-card reveal">
                <div class="section-badge mb-5">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">حساب جديد</span>
                </div>

                <h1 class="heading-md mb-4">أنشئ حسابك وابدأ من مساحة عملك الأولى</h1>
                <p class="text-body mb-6">سننشئ لك حسابًا، واشتراك Free، وWorkspace شخصية جاهزة لتبدأ مباشرة.</p>

                @if ($errors->any())
                    <div class="auth-feedback auth-feedback-error mb-6">
                        <strong>تعذر إنشاء الحساب.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="auth-form auth-form-two-col">
                    @csrf

                    <label class="auth-field">
                        <span>الاسم الكامل</span>
                        <input class="auth-input" type="text" name="name" value="{{ old('name') }}" required>
                    </label>

                    <label class="auth-field">
                        <span>البريد الإلكتروني</span>
                        <input class="auth-input" type="email" name="email" value="{{ old('email') }}" required>
                    </label>

                    <label class="auth-field">
                        <span>اسم الحساب التجاري</span>
                        <input class="auth-input" type="text" name="account_name" value="{{ old('account_name') }}" placeholder="اختياري - مثال: شركة المسار">
                    </label>

                    <label class="auth-field">
                        <span>اسم مساحة العمل</span>
                        <input class="auth-input" type="text" name="workspace_name" value="{{ old('workspace_name') }}" placeholder="اختياري - مثال: فريق التسويق">
                    </label>

                    <label class="auth-field">
                        <span>كلمة المرور</span>
                        <input class="auth-input" type="password" name="password" required>
                    </label>

                    <label class="auth-field">
                        <span>تأكيد كلمة المرور</span>
                        <input class="auth-input" type="password" name="password_confirmation" required>
                    </label>

                    <div class="cols-span-2">
                        <button type="submit" class="btn btn-primary btn-xl">إنشاء الحساب</button>
                    </div>
                </form>

                <p class="auth-note">
                    لديك حساب بالفعل؟
                    <a href="{{ route('login') }}">سجّل الدخول</a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
