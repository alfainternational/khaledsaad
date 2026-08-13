@extends('layouts.auth')
@section('layout', 'auth')

@section('title', 'نسيت كلمة المرور')
@section('heading', 'نسيت كلمة المرور؟')
{{-- الطمأنة بحقيقة لا بمواساة: ما يقلقه هو مصير مشاريعه لا كلمة المرور. --}}
@section('lead', 'أدخل بريد حسابك، ويصلك رابط تختار به كلمة مرور جديدة. مشاريعك وتقاريرك تبقى كما هي.')

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
    تذكرتها؟ <a href="{{ route('login') }}">ارجع لتسجيل الدخول</a>
@endsection
