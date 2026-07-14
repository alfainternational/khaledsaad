@extends('layouts.marketing', ['title' => 'تسجيل الدخول', 'description' => 'الدخول إلى منصة التسويق الاستراتيجي'])

@section('content')
<section class="dx">
    <div class="dx-shell dx-shell--narrow">
        <div class="dx-hero-head">
            <span class="dx-chip">دخول المستخدمين</span>
            <h1 class="dx-title">أهلاً بعودتك</h1>
            <p class="dx-sub">ادخل إلى مساحة عملك وأكمل من آخر خطوة وصلت إليها.</p>
        </div>

        @if ($errors->any())
            <div class="dx-alert" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status'))
            <div class="dx-alert dx-alert--success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="dx-card dx-form">
            @csrf
            <label class="dx-field">
                <span>البريد الإلكتروني</span>
                <input class="dx-input" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>

            <label class="dx-field">
                <span>كلمة المرور</span>
                <input class="dx-input" type="password" name="password" required>
            </label>

            <button type="submit" class="dx-submit">دخول الحساب</button>
        </form>

        <p class="dx-note"><a href="{{ route('password.request') }}">نسيت كلمة المرور؟</a></p>

        <div class="dx-social">
            <p class="dx-note">أو تابع عبر</p>
            <a class="dx-submit dx-submit--ghost" href="{{ route('social.redirect', 'google') }}">المتابعة عبر Google</a>
            <a class="dx-submit dx-submit--ghost" href="{{ route('social.redirect', 'facebook') }}">المتابعة عبر Facebook</a>
            <a class="dx-submit dx-submit--ghost" href="{{ route('social.redirect', 'twitter') }}">المتابعة عبر X</a>
            <a class="dx-submit dx-submit--ghost" href="{{ route('social.redirect', 'linkedin') }}">المتابعة عبر LinkedIn</a>
        </div>

        <p class="dx-note">ليس لديك حساب؟ <a href="{{ route('register') }}">أنشئ حساباً جديداً</a></p>
    </div>
</section>
@endsection
