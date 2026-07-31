@extends('layouts.auth')
@section('layout', 'auth')

@section('title', 'رمز الدخول')

@section('content')
    <h1>أدخل رمز الدخول</h1>
    <p class="muted">
        أرسلنا رمزًا من ستة أرقام إلى بريدك — يصلح لعشر دقائق.
        هذه الخطوة الإضافية فعّلتها أنت من صفحة أمان الحساب.
    </p>

    <form method="POST" action="{{ route('login.otp.verify') }}" class="form">
        @csrf
        <div class="field">
            <label class="field__label" for="code">الرمز</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}"
                maxlength="6" required autofocus autocomplete="one-time-code" class="otp-input">
            @error('code')<p class="field__error">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn btn--primary btn--block">ادخل</button>
    </form>

    <p class="muted"><a href="{{ route('login') }}">ارجع لتسجيل الدخول</a></p>
@endsection
