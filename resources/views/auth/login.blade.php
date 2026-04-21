@extends('layouts.marketing', ['title' => 'تسجيل الدخول', 'description' => 'الدخول إلى منصة التسويق الاستراتيجي'])

@section('content')
<section class="section-lg">
    <div class="site-container">
        <div class="auth-shell">
            <div class="auth-card reveal">
                <div class="section-badge mb-5">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">دخول المستخدمين</span>
                </div>

                <h1 class="heading-md mb-4">تسجيل الدخول إلى حسابك</h1>
                <p class="text-body mb-6">ادخل إلى مساحة عملك الحالية، تابع مشاريعك، وأكمل من آخر خطوة وصلت إليها.</p>

                @if ($errors->any())
                    <div class="auth-feedback auth-feedback-error mb-6">
                        <strong>تعذر إتمام تسجيل الدخول.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('status'))
                    <div class="auth-feedback auth-feedback-success mb-6">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf
                    <label class="auth-field">
                        <span>البريد الإلكتروني</span>
                        <input class="auth-input" type="email" name="email" value="{{ old('email') }}" required>
                    </label>

                    <label class="auth-field">
                        <span>كلمة المرور</span>
                        <input class="auth-input" type="password" name="password" required>
                    </label>

                    <button type="submit" class="btn btn-primary btn-xl">دخول الحساب</button>
                </form>

                <p class="auth-note">
                    ليس لديك حساب؟
                    <a href="{{ route('register') }}">أنشئ حسابًا جديدًا</a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
