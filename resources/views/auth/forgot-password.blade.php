@extends('layouts.marketing', ['title' => 'إعادة تعيين كلمة المرور', 'description' => 'استرجاع الدخول إلى منصة التسويق الاستراتيجي'])

@section('content')
<section class="dx">
    <div class="dx-shell dx-shell--narrow">
        <div class="dx-hero-head">
            <span class="dx-chip">استرجاع الحساب</span>
            <h1 class="dx-title">نسيت كلمة المرور؟</h1>
            <p class="dx-sub">أدخل بريدك الإلكتروني وسنرسل لك رابطاً لتعيين كلمة مرور جديدة.</p>
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

        <form method="POST" action="{{ route('password.email') }}" class="dx-card dx-form">
            @csrf
            <label class="dx-field">
                <span>البريد الإلكتروني</span>
                <input class="dx-input" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>

            <button type="submit" class="dx-submit">إرسال رابط الاستعادة</button>
        </form>

        <p class="dx-note">تذكّرت كلمة المرور؟ <a href="{{ route('login') }}">عُد لتسجيل الدخول</a></p>
    </div>
</section>
@endsection
