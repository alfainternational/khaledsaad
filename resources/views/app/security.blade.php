@extends('layouts.app')
@section('layout', 'form')

@section('title', 'أمان الحساب')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">حسابك</p>
            <h1>أمان الحساب والأجهزة المتصلة</h1>
        </div>
    </header>

    <section class="card" aria-labelledby="otp-heading">
        <h2 id="otp-heading" class="section-title">خطوة تحقق ثانية بالبريد</h2>
        <p class="muted">
            عند تفعيلها، كلمة المرور وحدها لا تكفي للدخول — يصلك رمز من ستة أرقام على بريدك
            مع كل تسجيل دخول، صالح لعشر دقائق.
        </p>

        <form method="POST" action="{{ route('app.security.otp') }}">
            @csrf
            <button type="submit" class="btn {{ $otpEnabled ? 'btn--ghost' : 'btn--primary' }}">
                {{ $otpEnabled ? 'ألغِ خطوة التحقق' : 'فعّل خطوة التحقق' }}
            </button>
            @if ($otpEnabled)
                <p class="tag tag--measured">مفعّلة الآن</p>
            @endif
        </form>
    </section>

    <section class="card" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading" class="section-title">الأجهزة المتصلة بحسابك ({{ $sessions->count() }})</h2>

        <ul class="list">
            @foreach ($sessions as $session)
                <li class="list__item">
                    <div>
                        <strong>{{ $session['agent'] }}</strong>
                        @if ($session['current'])
                            <span class="tag tag--measured">هذا الجهاز</span>
                        @endif
                        <p class="muted">{{ $session['ip'] }} · آخر نشاط {{ $session['last_active'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($sessions->count() > 1)
            <form method="POST" action="{{ route('app.security.logout-others') }}" class="form">
                @csrf
                <div class="field">
                    <label class="field__label" for="password">أكّد كلمة مرورك لإخراج بقية الأجهزة</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                    @error('password')<p class="field__error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn--primary">سجّل خروجي من الأجهزة الأخرى</button>
            </form>
        @endif
    </section>
@endsection
