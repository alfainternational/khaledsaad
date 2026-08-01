@extends('layouts.auth')
@section('layout', 'auth')

@section('title', 'كلمة مرور جديدة')
@section('heading', 'اختر كلمة مرور جديدة')
{{-- أفعال أمر بترتيب التنفيذ: احفظ ثم ادخل ثم أكمل. --}}
@section('lead', 'احفظ كلمة المرور الجديدة، ثم سجّل دخولك وأكمل من حيث توقفت.')

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
    <a href="{{ route('login') }}">ارجع لتسجيل الدخول</a>
@endsection
