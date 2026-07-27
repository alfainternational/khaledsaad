@extends('layouts.auth')

@section('title', 'كلمة مرور جديدة')
@section('heading', 'اختر كلمة مرور جديدة')
@section('lead', 'بعد حفظ كلمة المرور الجديدة، يمكنك تسجيل الدخول والمتابعة من حيث توقفت.')

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
            <span class="field__label">تأكيد كلمة المرور الجديدة</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>

        <button type="submit" class="btn btn--primary btn--block">احفظ كلمة المرور</button>
    </form>
@endsection

@section('alt')
    <a href="{{ route('login') }}">العودة إلى تسجيل الدخول</a>
@endsection
