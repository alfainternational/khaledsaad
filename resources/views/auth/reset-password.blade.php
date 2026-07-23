@extends('layouts.auth')

@section('title', 'كلمة مرور جديدة')
@section('heading', 'اختر كلمة مرور جديدة')
@section('lead', 'بعدها تدخل مباشرة وتكمل من حيث توقفت.')

@section('form')
    <form method="POST" action="{{ route('password.update') }}" class="form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label class="field">
            <span class="field__label">بريدك الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
        </label>

        <label class="field">
            <span class="field__label">كلمة المرور الجديدة</span>
            <input type="password" name="password" required autofocus autocomplete="new-password">
            <span class="field__help">ثمانية أحرف على الأقل.</span>
        </label>

        <label class="field">
            <span class="field__label">أعدها مرة أخرى</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>

        <button type="submit" class="btn btn--primary btn--block">احفظ وادخل</button>
    </form>
@endsection

@section('alt')
    <a href="{{ route('login') }}">ارجع لتسجيل الدخول</a>
@endsection
