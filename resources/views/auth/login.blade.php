@extends('layouts.auth')
@section('layout', 'auth')

@section('title', 'تسجيل الدخول')
@section('heading', 'أهلًا بعودتك')
@section('lead', 'سجّل الدخول للمتابعة من حيث توقفت والوصول إلى تقاريرك ومهامك المحفوظة.')

@section('context')
    @if ($startTool !== null)
        <div class="auth-intent" role="note">
            <span class="auth-intent__tag">{{ $startTool['category'] }}</span>
            <strong>{{ $startTool['title'] }}</strong>
            <p>سننقلك إلى هذا التشخيص مباشرة بعد تسجيل الدخول.</p>
        </div>
    @endif
@endsection

@section('form')
    <form method="POST" action="{{ route('login') }}" class="form">
        @csrf

        <label class="field">
            <span class="field__label">البريد الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        </label>

        <label class="field">
            <span class="field__label">كلمة المرور</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>

        <p class="auth-card__forgot">
            <a href="{{ route('password.request') }}">نسيت كلمة المرور؟</a>
        </p>

        <label class="field field--inline">
            <input type="checkbox" name="remember" value="1">
            <span>أبقني مسجلًا</span>
        </label>

        <button type="submit" class="btn btn--primary btn--block">سجّل الدخول</button>
    </form>
@endsection

@section('alt')
    ليس لديك حساب؟ <a href="{{ route('register', $startTool !== null ? ['tool' => $startTool['key']] : []) }}">أنشئ حسابًا</a>
    <span class="auth-card__sep" aria-hidden="true">·</span>
    <a href="{{ route('tools.index') }}">استكشف التشخيصات المتاحة</a>
@endsection
