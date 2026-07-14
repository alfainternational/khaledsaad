@extends('layouts.marketing', ['title' => 'تعيين كلمة مرور جديدة', 'description' => 'تعيين كلمة مرور جديدة لحسابك في منصة التسويق الاستراتيجي'])

@section('content')
<section class="dx">
    <div class="dx-shell dx-shell--narrow">
        <div class="dx-hero-head">
            <span class="dx-chip">استرجاع الحساب</span>
            <h1 class="dx-title">تعيين كلمة مرور جديدة</h1>
            <p class="dx-sub">اختر كلمة مرور قوية لا تقل عن ٨ أحرف لتأمين حسابك.</p>
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

        <form method="POST" action="{{ route('password.update') }}" class="dx-card dx-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label class="dx-field">
                <span>البريد الإلكتروني</span>
                <input class="dx-input" type="email" name="email" value="{{ old('email', $email) }}" required readonly>
            </label>

            <label class="dx-field">
                <span>كلمة المرور الجديدة</span>
                <input class="dx-input" type="password" name="password" required autofocus autocomplete="new-password">
            </label>

            <label class="dx-field">
                <span>تأكيد كلمة المرور</span>
                <input class="dx-input" type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <button type="submit" class="dx-submit">حفظ كلمة المرور</button>
        </form>

        <p class="dx-note">تذكّرت كلمة المرور؟ <a href="{{ route('login') }}">عُد لتسجيل الدخول</a></p>
    </div>
</section>
@endsection
