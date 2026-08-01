@extends('layouts.auth')
@section('layout', 'auth')

@section('title', 'رمز الدخول')
@section('heading', 'أدخل رمز الدخول')

{{-- سبب ظهور الخطوة يُذكر هنا: من نسي أنه فعّلها يظنّها عطلًا فيتوقف. --}}
@section('lead')
    أرسلنا رمزًا من ستة أرقام إلى بريدك، صالحًا لعشر دقائق.
    هذه الخطوة الإضافية فعّلتها أنت من صفحة أمان الحساب.
@endsection

@section('form')
    <form method="POST" action="{{ route('login.otp.verify') }}" class="form">
        @csrf
        <div class="field">
            <label class="field__label" for="code">الرمز</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}"
                maxlength="6" required autofocus autocomplete="one-time-code" class="otp-input">
        </div>

        <button type="submit" class="btn btn--primary btn--block">تحقّق وادخل</button>
    </form>
@endsection

@section('alt')
    <a href="{{ route('login') }}">ارجع لتسجيل الدخول</a>
@endsection
