@extends('layouts.auth')

@section('title', 'نسيت كلمة المرور')
@section('heading', 'نسيت كلمة المرور؟')
@section('lead', 'أدخل بريد حسابك، وسنرسل رابطًا يتيح لك اختيار كلمة مرور جديدة. ستبقى مشاريعك وتقاريرك محفوظة.')

@section('context')
    @if (session('status'))
        <p class="alert alert--success" role="status">{{ session('status') }}</p>
    @endif
@endsection

@section('form')
    <form method="POST" action="{{ route('password.email') }}" class="form">
        @csrf

        <label class="field">
            <span class="field__label">بريدك الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            <span class="field__help">البريد نفسه الذي أنشأت به الحساب.</span>
        </label>

        <button type="submit" class="btn btn--primary btn--block">أرسل رابط الاستعادة</button>
    </form>
@endsection

@section('alt')
    تذكرت كلمة المرور؟ <a href="{{ route('login') }}">العودة إلى تسجيل الدخول</a>
@endsection
