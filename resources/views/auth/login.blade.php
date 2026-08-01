@extends('layouts.auth')
@section('layout', 'auth')

@section('title', 'تسجيل الدخول')
{{-- العنوان يقول ما تكسبه من الدخول، لا تحية عامة تصلح لأي موقع. --}}
@section('heading', 'أكمل من حيث توقفت')
@section('lead', 'سجّل دخولك لتفتح مشاريعك وتقاريرك ومهامك كما تركتها.')

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
            {{-- «على هذا الجهاز» تحدّد نطاق الخيار: بدونها يُفهم أنه يسري على كل جهاز. --}}
            <span>أبقني مسجّلًا على هذا الجهاز</span>
        </label>

        <button type="submit" class="btn btn--primary btn--block">سجّل الدخول</button>
    </form>
@endsection

@section('alt')
    ليس لديك حساب؟ <a href="{{ route('register', $startTool !== null ? ['tool' => $startTool['key']] : []) }}">أنشئ حسابًا</a>
    <span class="auth-card__sep" aria-hidden="true">·</span>
    <a href="{{ route('tools.index') }}">اطّلع على التشخيصات</a>
@endsection
